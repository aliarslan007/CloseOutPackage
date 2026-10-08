<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageChecklistResponse;
use App\Domains\Markets\Models\Market;
use App\Domains\PdfGeneration\DTOs\PdfChunkDTO;
use App\Domains\PdfGeneration\Services\PdfChunkPlanner;
use App\Domains\PdfGeneration\Services\PdfMergeService;
use App\Domains\PdfGeneration\Services\PdfPhotoItemResolver;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\ChecklistItem;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplatePictureCategory;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('private');
    config(['cop.pdf.chunk_pages' => 8]);
});

it('verifies memory, CPU, and chunk scaling when generating a COP with 200 photos', function (): void {
    $tech = User::factory()->create();
    $market = Market::factory()->create();
    $project = Project::factory()->create(['market_id' => $market->id, 'name' => 'Verizon Cell Tower 200-Photo Scale Test']);
    $template = Template::factory()->create(['market_id' => $market->id]);
    $pre = $template->sections()->where('stage', 'pre_construction')->firstOrFail();

    // Create 10 categories with 20 photos each = 200 photos
    $categories = [];
    for ($c = 1; $c <= 10; $c++) {
        $categories[] = TemplatePictureCategory::factory()->create([
            'template_section_id' => $pre->id,
            'name' => 'Antenna Sector '.chr(64 + $c),
        ]);
    }

    $checklist = ChecklistItem::factory()->create(['template_id' => $template->id, 'label' => 'Structural check passed']);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
    ]);

    $package->siteInfo()->create([
        'site_name' => 'High-Density Cell Site 404',
        'address' => '1000 Wireless Way, Dallas, TX 75001',
        'vzw_engineer_name' => 'Lead Engineer',
        'vzw_engineer_email' => 'engineer@vzw.com',
        'author_name' => 'Senior Tech',
        'author_email' => 'tech@contractor.com',
        'pm_name' => 'Project Manager',
        'pm_email' => 'pm@contractor.com',
    ]);

    PackageChecklistResponse::factory()->create([
        'closeout_package_id' => $package->id,
        'checklist_item_id' => $checklist->id,
        'answer' => true,
    ]);

    // Create a real 1200x1200 JPEG fixture to simulate the upload-time pdf conversion
    $tempDir = sys_get_temp_dir().'/pdf-scale-test-'.uniqid();
    @mkdir($tempDir, 0777, true);
    $sampleImage = $tempDir.'/sample-1200.jpg';
    $img = imagecreatetruecolor(1200, 900);
    $bg = imagecolorallocate($img, 30, 80, 160);
    imagefilledrectangle($img, 0, 0, 1200, 900, (int) $bg);
    imagejpeg($img, $sampleImage, 80);
    imagedestroy($img);

    $stage = $package->stages()->create([
        'stage' => 'pre_construction',
        'status' => 'approved',
    ]);

    $selections = [];
    foreach ($categories as $cat) {
        $selections[] = $stage->categorySelections()->create([
            'template_picture_category_id' => $cat->id,
            'is_selected' => true,
            'picture_count' => 20,
            'user_heading' => $cat->name,
        ]);
    }

    // Build 200 photos attached to the package (20 per category selection)
    $photos = collect();
    for ($i = 0; $i < 200; $i++) {
        $selection = $selections[$i % 10];
        $photo = Photo::factory()->create([
            'closeout_package_id' => $package->id,
            'package_stage_id' => $stage->id,
            'package_category_selection_id' => $selection->id,
            'uploaded_by' => $tech->id,
            'is_approved' => true,
        ]);

        $photoFile = $tempDir.'/photo-'.$i.'.jpg';
        copy($sampleImage, $photoFile);

        // Attach to spatie media library with pre-generated pdf conversion
        $media = $photo->addMedia($photoFile)->toMediaCollection(Photo::COLLECTION);
        $pdfPath = $media->getPath('pdf');
        @mkdir(dirname($pdfPath), 0777, true);
        copy($sampleImage, $pdfPath);
        $media->markAsConversionGenerated('pdf', true);
        $media->save();

        $photos->push($photo);
    }

    expect($photos)->toHaveCount(200);

    // -------------------------------------------------------------
    // PHASE 1: Benchmark Photo Metadata Resolution (PrepareChunkedPdfJob entrypoint)
    // -------------------------------------------------------------
    gc_collect_cycles();
    $startMem = memory_get_usage(true);
    $startTime = microtime(true);

    $resolvedItems = app(PdfPhotoItemResolver::class)->resolve($photos, optimize: false);

    $resolverDurationMs = (microtime(true) - $startTime) * 1000;
    $resolverMemMb = (memory_get_usage(true) - $startMem) / (1024 * 1024);

    expect($resolvedItems)->toHaveCount(200);
    // Resolving metadata for 200 photos must be sub-second and minimal memory
    expect($resolverDurationMs)->toBeLessThan(1000.0);
    expect($resolverMemMb)->toBeLessThan(20.0);

    // -------------------------------------------------------------
    // PHASE 2: Benchmark Chunk Planning (PdfChunkPlanner)
    // -------------------------------------------------------------
    $plannerStartMem = memory_get_usage(true);
    $plannerStartTime = microtime(true);

    $plan = app(PdfChunkPlanner::class)->plan($package, $resolvedItems, 'R1.0', '2026-09-19');

    $plannerDurationMs = (microtime(true) - $plannerStartTime) * 1000;
    $plannerPeakMemMb = memory_get_peak_usage(true) / (1024 * 1024);

    expect($plan['chunks'])->not->toBeEmpty();
    // 200 photos = 50 photo pages (4 per page) + ~5-6 front section pages = ~55-56 pages total
    expect($plan['totalPages'])->toBeGreaterThanOrEqual(53);
    expect($plan['totalPages'])->toBeLessThanOrEqual(60);

    // 5 front section chunks (cover, scope, siteDetails, checklist, pictures) + 7 photo chunks = 11-12 chunks
    expect(count($plan['chunks']))->toBeGreaterThanOrEqual(10);
    expect(count($plan['chunks']))->toBeLessThanOrEqual(14);

    // Verify all chunks are contiguous and cover every single page from 1 to totalPages
    $cursor = 1;
    /** @var PdfChunkDTO $chunk */
    foreach ($plan['chunks'] as $chunk) {
        expect($chunk->firstPage)->toBe($cursor);
        expect($chunk->lastPage)->toBeGreaterThanOrEqual($chunk->firstPage);
        $chunkPageCount = $chunk->lastPage - $chunk->firstPage + 1;
        // Front section can be <= 8, photo sections strictly <= 8
        expect($chunkPageCount)->toBeLessThanOrEqual(8);
        $cursor = $chunk->lastPage + 1;
    }
    expect($cursor - 1)->toBe($plan['totalPages']);

    // -------------------------------------------------------------
    // PHASE 3: Benchmark Ghostscript Merge of 200-Photo Chunks
    // -------------------------------------------------------------
    $gs = (string) config('cop.pdf.gs_binary', 'gs');
    $chunkFiles = [];

    // Synthesize the 7-8 chunk PDFs using ghostscript
    foreach ($plan['chunks'] as $idx => $chunk) {
        $chunkPages = $chunk->lastPage - $chunk->firstPage + 1;
        $chunkPs = $tempDir.'/chunk-'.$idx.'.ps';
        $chunkPdf = $tempDir.'/chunk-'.$idx.'.pdf';

        $psContent = '';
        for ($p = $chunk->firstPage; $p <= $chunk->lastPage; $p++) {
            $psContent .= "%!PS\n/Helvetica findfont 18 scalefont setfont 50 700 moveto (Package 200-Photo Chunk {$idx} Page {$p} of {$plan['totalPages']}) show showpage\n";
        }
        file_put_contents($chunkPs, $psContent);

        $process = new Process([$gs, '-q', '-dNOPAUSE', '-dBATCH', '-sDEVICE=pdfwrite', '-sOutputFile='.$chunkPdf, $chunkPs]);
        $process->mustRun();

        $chunkFiles[] = $chunkPdf;
    }

    $mergedPdf = $tempDir.'/final-merged-200-photos.pdf';
    $mergeStart = microtime(true);
    $mergeStartMem = memory_get_usage(true);

    app(PdfMergeService::class)->merge($chunkFiles, $mergedPdf);

    $mergeDurationMs = (microtime(true) - $mergeStart) * 1000;
    $mergeMemMb = (memory_get_usage(true) - $mergeStartMem) / (1024 * 1024);

    expect(is_file($mergedPdf))->toBeTrue();
    expect(substr((string) file_get_contents($mergedPdf), 0, 4))->toBe('%PDF');

    // Verify merged PDF page count matches total pages exactly
    $countProcess = new Process([$gs, '-q', '-dNODISPLAY', '-c', '('.$mergedPdf.') (r) file runpdfbegin pdfpagecount = quit']);
    $countProcess->mustRun();
    expect((int) trim($countProcess->getOutput()))->toBe($plan['totalPages']);

    $mergedFileSizeKb = filesize($mergedPdf) / 1024;
    $totalPeakMemMb = memory_get_peak_usage(true) / (1024 * 1024);

    // -------------------------------------------------------------
    // ASSERT PRODUCTION SAFETY CEILINGS
    // -------------------------------------------------------------
    // 1. Worker RAM limit is 512MB. PHP peak memory must stay well under 128MB.
    expect($totalPeakMemMb)->toBeLessThan(128.0);
    // 2. Planning must take < 500ms
    expect($plannerDurationMs)->toBeLessThan(500.0);
    // 3. Ghostscript merge of all 200 photo chunks must take < 2000ms (2 seconds)
    expect($mergeDurationMs)->toBeLessThan(2000.0);

    // Clean up temp directory
    $files = glob($tempDir.'/*');
    if ($files !== false) {
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
    @rmdir($tempDir);
});
