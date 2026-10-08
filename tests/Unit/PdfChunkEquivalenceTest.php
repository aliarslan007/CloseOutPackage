<?php

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageChecklistResponse;
use App\Domains\Markets\Models\Market;
use App\Domains\PdfGeneration\Services\PdfChunkPlanner;
use App\Domains\PdfGeneration\Services\PdfMergeService;
use App\Domains\PdfGeneration\Services\PdfPhotoPageRenderer;
use App\Domains\PdfGeneration\Services\PdfRenderService;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\ChecklistItem;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplatePictureCategory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Small window so photo pages split across multiple chunks.
    config(['cop.pdf.chunk_pages' => 2]);

    $this->fixture = function (): CloseoutPackage {
        $market = Market::factory()->create();
        $project = Project::factory()->create(['market_id' => $market->id, 'name' => 'Lomas Peak']);
        $template = Template::factory()->create(['market_id' => $market->id]);
        $pre = $template->sections()->where('stage', 'pre_construction')->firstOrFail();
        TemplatePictureCategory::factory()->create(['template_section_id' => $pre->id, 'name' => 'MMP']);
        TemplatePictureCategory::factory()->create(['template_section_id' => $pre->id, 'name' => 'Grounding']);
        $checklist = ChecklistItem::factory()->create(['template_id' => $template->id, 'label' => 'Site access granted?']);

        $package = CloseoutPackage::factory()->create([
            'project_id' => $project->id,
            'template_id' => $template->id,
        ]);
        $package->siteInfo()->create([
            'site_name' => 'Lomas Peak',
            'address' => '9654 Jamboree Road, Orange, CA 92869',
            'vzw_engineer_name' => 'Ryan Temple',
            'vzw_engineer_email' => 'ryan.temple@verizonwireless.com',
            'author_name' => 'Mike Mullerleile',
            'author_email' => 'mmullerleile@cikpower.com',
            'pm_name' => 'Project Manager',
            'pm_email' => 'pm@example.com',
        ]);
        PackageChecklistResponse::factory()->create([
            'closeout_package_id' => $package->id,
            'checklist_item_id' => $checklist->id,
            'answer' => true,
        ]);

        return $package;
    };
});

it('windowed photo rendering reproduces the legacy photo fragment byte-for-byte', function (): void {
    $package = ($this->fixture)();

    // 10 photos across two categories: 4 pages in the legacy renderer
    // (4 + 4 / 4 + 4 + 1), exercising multi-page categories and headings.
    $photoItems = [];
    foreach (['MMP', 'Grounding'] as $index => $category) {
        for ($i = 1; $i <= 5; $i++) {
            $photoItems[] = [
                'path' => '/tmp/missing-'.($index * 5 + $i).'.jpg',
                'heading' => $category,
                'category' => $category,
            ];
        }
    }

    $renderService = app(PdfRenderService::class);
    $result = $renderService->renderSections($package, $photoItems, 'R1.0', '2026-08-03');

    expect($result['sections']['photoPages']['pages'])->toBe(4);

    $renderer = new PdfPhotoPageRenderer;
    $windowed = '';
    $photoStartPage = $result['totalPages'] - 4 + 1;

    foreach ([0, 1, 2, 3] as $offset) {
        $windowed .= $renderer->renderWindow(
            $package,
            $photoItems,
            $result['totalPages'],
            'R1.0',
            CarbonImmutable::parse('2026-08-03'),
            $result['logoPath'],
            $result['partnerLogoPath'],
            $photoStartPage,
            $photoStartPage + $offset,
            1,
        );
    }

    expect($windowed)->toBe($result['sections']['photoPages']['html']);
});

it('plans contiguous chunks whose union covers every page exactly once', function (): void {
    $package = ($this->fixture)();

    $photoItems = [];
    for ($i = 1; $i <= 9; $i++) {
        $photoItems[] = ['path' => '/tmp/missing-'.$i.'.jpg', 'heading' => 'MMP', 'category' => 'MMP'];
    }

    $plan = app(PdfChunkPlanner::class)->plan($package, $photoItems, 'R1.0', '2026-08-03');

    expect($plan['chunks'])->not->toBeEmpty()
        ->and($plan['chunks'][0]->firstPage)->toBe(1);

    $cursor = 1;

    foreach ($plan['chunks'] as $chunk) {
        expect($chunk->firstPage)->toBe($cursor)
            ->and($chunk->lastPage)->toBeGreaterThanOrEqual($chunk->firstPage);

        if (str_starts_with($chunk->id, 'photoPages')) {
            expect($chunk->lastPage - $chunk->firstPage + 1)->toBeLessThanOrEqual(2); // chunk_pages
        }

        $cursor = $chunk->lastPage + 1;
    }

    expect($cursor - 1)->toBe($plan['totalPages']);
});

it('merges chunk PDFs with ghostscript preserving page count', function (): void {
    $gs = (string) config('cop.pdf.gs_binary', 'gs');

    $makeOnePagePdf = function (string $path, string $text) use ($gs): void {
        $ps = '%!PS\n/Helvetica findfont 24 scalefont setfont 72 720 moveto ('.$text.') show showpage';
        file_put_contents($path, $ps);

        $process = new Process([$gs, '-q', '-dNOPAUSE', '-dBATCH', '-sDEVICE=pdfwrite', '-sOutputFile='.str_replace('.ps', '.pdf', $path), $path]);
        $process->mustRun();
    };

    $dir = sys_get_temp_dir().'/'.uniqid('gs-merge-', true);
    mkdir($dir);

    $makeOnePagePdf($dir.'/a.ps', 'Page one');
    $makeOnePagePdf($dir.'/b.ps', 'Page two');

    $merged = $dir.'/merged.pdf';

    app(PdfMergeService::class)->merge([$dir.'/a.pdf', $dir.'/b.pdf'], $merged);

    expect(is_file($merged))->toBeTrue()
        ->and(substr((string) file_get_contents($merged), 0, 4))->toBe('%PDF');

    $count = new Process([$gs, '-q', '-dNODISPLAY', '-c', '('.$merged.') (r) file runpdfbegin pdfpagecount = quit']);
    $count->mustRun();

    expect(trim($count->getOutput()))->toBe('2');

    @unlink($dir.'/a.pdf');
    @unlink($dir.'/b.ps');
    @unlink($dir.'/a.ps');
    @unlink($dir.'/b.pdf');
    @unlink($merged);
    @rmdir($dir);
});
