<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Markets\Models\Market;
use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\Projects\Models\Project;
use App\Domains\ScopeOfWork\Enums\CompositeStatus;
use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowAnnotatedImage;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\ScopeOfWork\Services\SowPdfRenderService;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');
});

/**
 * @param  list<array{group?: string|null, include?: bool}>  $photos
 * @return array{report: SowReport, groups: array<string, int>}
 */
function sowPdfGroupFixture(array $photos): array
{
    $market = Market::factory()->create(['is_active' => true]);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $supervisor->markets()->attach($market->id);

    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $supervisor->id,
    ]);

    $report = SowReport::create([
        'market_id' => $market->id,
        'created_by' => $supervisor->id,
        'project_id' => $project->id,
        'status' => SowStatus::DRAFT,
        'title' => 'PDF Grouping SOW',
        'site_name' => 'Grouping Site',
        'revision' => 1,
        'revision_date' => now()->toDateString(),
    ]);

    // Groups are created on demand, in first-seen order.
    $groups = [];
    foreach ($photos as $index => $photo) {
        $name = $photo['group'] ?? null;
        if ($name !== null && ! isset($groups[$name])) {
            $groups[$name] = $report->imageGroups()->create([
                'name' => $name,
                'sort_order' => count($groups),
            ])->id;
        }

        $asset = MediaAsset::factory()->create([
            'user_id' => $supervisor->id,
            'market_id' => $market->id,
            'project_id' => $project->id,
            'status' => MediaAssetStatus::READY->value,
        ]);

        SowAnnotatedImage::create([
            'sow_report_id' => $report->id,
            'media_asset_id' => $asset->id,
            'sow_image_group_id' => $name === null ? null : $groups[$name],
            'sort_order' => $index,
            'annotation_layer' => [],
            'composite_status' => CompositeStatus::NOT_NEEDED,
            'include_in_pdf' => $photo['include'] ?? true,
        ]);
    }

    return compact('report', 'groups');
}

/**
 * Headings in document order.
 *
 * @return list<string>
 */
function pdfHeadings(string $html): array
{
    preg_match_all('/<div class="section-heading-title">\s*(.*?)\s*<\/div>/s', $html, $matches);

    return array_values(array_filter(array_map(
        static fn (string $heading): string => trim(preg_replace('/\s+/', ' ', $heading) ?? ''),
        $matches[1],
    )));
}

it('renders one headed section per group in group order', function (): void {
    ['report' => $report] = sowPdfGroupFixture([
        ['group' => 'Site Plan'],
        ['group' => 'Site Plan'],
        ['group' => 'Fiber Pathway'],
        ['group' => 'Fiber Pathway'],
    ]);

    $rendered = app(SowPdfRenderService::class)->render($report);

    $headings = pdfHeadings($rendered['html']);

    // "Summary" and "Site access notes" are the pre-existing page-2 headings;
    // "Survey Checklist" heads page 3. Group headings follow.
    expect($headings)->toContain('Site Plan')
        ->and($headings)->toContain('Fiber Pathway');

    // Group headings appear after the checklist heading, in group order.
    $groupHeadings = array_values(array_intersect($headings, ['Site Plan', 'Fiber Pathway']));
    expect($groupHeadings)->toBe(['Site Plan', 'Fiber Pathway']);

    // 4 photos across 2 groups = 2 pages (2 per page).
    expect($rendered['totalPages'])->toBe(5);
});

it('marks continuation pages of a group with (continued)', function (): void {
    // 3 photos in one group = 2 pages: [2 photos], [1 photo continued].
    ['report' => $report] = sowPdfGroupFixture([
        ['group' => 'Site Plan'],
        ['group' => 'Site Plan'],
        ['group' => 'Site Plan'],
    ]);

    $rendered = app(SowPdfRenderService::class)->render($report);
    $headings = pdfHeadings($rendered['html']);

    expect($rendered['totalPages'])->toBe(5)
        ->and($headings)->toContain('Site Plan')
        ->and($headings)->toContain('Site Plan (continued)');
});

it('renders ungrouped photos under the Other Photos heading', function (): void {
    ['report' => $report] = sowPdfGroupFixture([
        ['group' => 'Site Plan'],
        ['group' => null],
        ['group' => null],
    ]);

    $rendered = app(SowPdfRenderService::class)->render($report);
    $headings = pdfHeadings($rendered['html']);

    expect($headings)->toContain('Other Photos');
});

it('omits the heading when a group has no photos included in the PDF', function (): void {
    // "Fiber Pathway" exists but every one of its photos is excluded.
    ['report' => $report] = sowPdfGroupFixture([
        ['group' => 'Site Plan'],
        ['group' => 'Fiber Pathway', 'include' => false],
        ['group' => 'Fiber Pathway', 'include' => false],
    ]);

    $rendered = app(SowPdfRenderService::class)->render($report);
    $headings = pdfHeadings($rendered['html']);

    expect($headings)->toContain('Site Plan')
        ->and($headings)->not->toContain('Fiber Pathway')
        // Only the single included photo renders → one photo page.
        ->and($rendered['totalPages'])->toBe(4);
});

it('omits the Other Photos heading when every photo is grouped', function (): void {
    ['report' => $report] = sowPdfGroupFixture([
        ['group' => 'Site Plan'],
        ['group' => 'Fiber Pathway'],
    ]);

    $rendered = app(SowPdfRenderService::class)->render($report);

    expect(pdfHeadings($rendered['html']))->not->toContain('Other Photos');
});

it('renders no headings and the same page count for a report without groups', function (): void {
    ['report' => $report] = sowPdfGroupFixture([
        ['group' => null],
        ['group' => null],
        ['group' => null],
    ]);

    $rendered = app(SowPdfRenderService::class)->render($report);
    $headings = pdfHeadings($rendered['html']);

    // Pre-existing headings only — grouping adds nothing to a group-less report.
    expect($headings)->toBe(['Summary', 'Site access notes', 'Survey Checklist'])
        ->and($headings)->not->toContain('Other Photos')
        // 3 photos = 2 photo pages (2 + 1) → unchanged from the pre-grouping layout.
        ->and($rendered['totalPages'])->toBe(5);
});

it('skips photo pages entirely when no photo is included', function (): void {
    ['report' => $report] = sowPdfGroupFixture([
        ['group' => 'Site Plan', 'include' => false],
    ]);

    $rendered = app(SowPdfRenderService::class)->render($report);

    expect($rendered['totalPages'])->toBe(3)
        ->and(pdfHeadings($rendered['html']))->not->toContain('Site Plan');
});

it('embeds the medium conversion in pdf instead of raw master asset when available', function (): void {
    ['report' => $report] = sowPdfGroupFixture([
        ['group' => 'Antennas'],
    ]);

    $image = $report->annotatedImages()->firstOrFail();
    $asset = $image->mediaAsset;

    $media = $asset->addMedia(copTestImage('image/jpeg', 2400, 1800))
        ->preservingOriginal()
        ->toMediaCollection(MediaAsset::COLLECTION);

    $conversionDir = dirname($media->getPath()).'/conversions';
    if (! is_dir($conversionDir)) {
        mkdir($conversionDir, 0755, true);
    }
    $mediumPath = $media->getPath('medium');
    $mediumImage = copTestImage('image/jpeg', 800, 600);
    $mediumBytes = file_get_contents($mediumImage->getRealPath());
    file_put_contents($mediumPath, $mediumBytes);

    $rendered = app(SowPdfRenderService::class)->render($report);
    $html = $rendered['html'];

    $expectedDataUri = 'data:image/jpeg;base64,'.base64_encode($mediumBytes);
    expect($html)->toContain($expectedDataUri);
});
