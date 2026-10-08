<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Markets\Models\Market;
use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\PdfGeneration\Services\BrowsershotPdfService;
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

function sowAnnotationFixture(): array
{
    $market = Market::factory()->create(['is_active' => true]);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);

    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);

    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $supervisor->markets()->attach($market->id);
    $project->fieldSupervisors()->attach($supervisor->id);

    $report = SowReport::create([
        'market_id' => $market->id,
        'created_by' => $supervisor->id,
        'project_id' => $project->id,
        'status' => SowStatus::DRAFT,
        'title' => 'Canvas Test SOW',
    ]);

    $asset = MediaAsset::factory()->create([
        'user_id' => $supervisor->id,
        'market_id' => $market->id,
        'project_id' => $project->id,
        'status' => MediaAssetStatus::READY->value,
    ]);

    $image = SowAnnotatedImage::create([
        'sow_report_id' => $report->id,
        'media_asset_id' => $asset->id,
        'sort_order' => 1,
        'annotation_layer' => [],
        'composite_status' => CompositeStatus::NOT_NEEDED,
    ]);

    return compact('market', 'manager', 'project', 'supervisor', 'report', 'asset', 'image');
}

it('successfully saves canvas box annotations with norm_x and norm_y', function (): void {
    extract(sowAnnotationFixture());

    $boxNodeId = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d';

    $payload = [
        'layer' => [
            'base_width' => 1200,
            'nodes' => [
                [
                    'id' => $boxNodeId,
                    'type' => 'box',
                    'z_index' => 0,
                    'opacity' => 1.0,
                    'stroke_color' => '#EF4444',
                    'fill_color' => '#EF4444',
                    'norm_stroke_width' => 0.0033,
                    'norm_x' => 0.25,
                    'norm_y' => 0.35,
                    'norm_width' => 0.2,
                    'norm_height' => 0.15,
                    'border_radius' => 4,
                    'dashed' => false,
                    'text' => 'Demarcation Box 1',
                    'norm_font_size' => 0.015,
                    'text_color' => '#FFFFFF',
                    'font_weight' => 'bold',
                ],
            ],
        ],
    ];

    $response = $this->actingAs($supervisor)
        ->putJson(route('sow.images.update', ['report' => $report->id, 'image' => $image->id]), $payload);

    $response->assertOk()
        ->assertJsonPath('success', true);

    $image->refresh();
    expect($image->annotation_layer)->not->toBeEmpty()
        ->and($image->annotation_layer['nodes'][0]['norm_x'])->toBe(0.25)
        ->and($image->annotation_layer['nodes'][0]['norm_y'])->toBe(0.35)
        ->and($image->annotation_layer['nodes'][0]['type'])->toBe('box')
        ->and($image->composite_status)->toBe(CompositeStatus::PENDING);
});

it('successfully saves mixed canvas shapes including arrow, dot, text_label and dashed_line', function (): void {
    extract(sowAnnotationFixture());

    $payload = [
        'layer' => [
            'base_width' => 1200,
            'nodes' => [
                [
                    'id' => '11111111-1111-1111-1111-111111111111',
                    'type' => 'arrow',
                    'z_index' => 0,
                    'opacity' => 0.9,
                    'stroke_color' => '#3B82F6',
                    'fill_color' => '#3B82F6',
                    'norm_stroke_width' => 0.004,
                    'norm_points' => [0.1, 0.1, 0.3, 0.3],
                    'arrowhead_style' => 'solid',
                    'dashed' => false,
                ],
                [
                    'id' => '22222222-2222-2222-2222-222222222222',
                    'type' => 'dot',
                    'z_index' => 1,
                    'opacity' => 1.0,
                    'stroke_color' => '#10B981',
                    'fill_color' => '#10B981',
                    'norm_stroke_width' => 0.002,
                    'norm_x' => 0.45,
                    'norm_y' => 0.55,
                    'norm_radius' => 0.008,
                ],
                [
                    'id' => '33333333-3333-3333-3333-333333333333',
                    'type' => 'text_label',
                    'z_index' => 2,
                    'opacity' => 1.0,
                    'stroke_color' => '#000000',
                    'fill_color' => '#000000',
                    'norm_stroke_width' => 0.002,
                    'norm_x' => 0.6,
                    'norm_y' => 0.7,
                    'text' => 'Antenna Port 4',
                    'norm_font_size' => 0.018,
                    'text_color' => '#FFFFFF',
                    'font_weight' => 'bold',
                ],
                [
                    'id' => '44444444-4444-4444-4444-444444444444',
                    'type' => 'dashed_line',
                    'z_index' => 3,
                    'opacity' => 0.8,
                    'stroke_color' => '#F59E0B',
                    'fill_color' => '#F59E0B',
                    'norm_stroke_width' => 0.003,
                    'norm_points' => [0.05, 0.05, 0.2, 0.2, 0.35, 0.35],
                    'dash_pattern' => [8, 6],
                ],
            ],
        ],
    ];

    $response = $this->actingAs($supervisor)
        ->putJson(route('sow.images.update', ['report' => $report->id, 'image' => $image->id]), $payload);

    $response->assertOk()
        ->assertJsonPath('success', true);

    $image->refresh();
    expect($image->annotation_layer['nodes'])->toHaveCount(4);
});

it('supports portrait aspect vertical coordinates with norm_y exceeding 1.0', function (): void {
    extract(sowAnnotationFixture());

    $payload = [
        'layer' => [
            'base_width' => 1200,
            'nodes' => [
                [
                    'id' => '55555555-5555-5555-5555-555555555555',
                    'type' => 'box',
                    'z_index' => 0,
                    'opacity' => 1.0,
                    'stroke_color' => '#EF4444',
                    'fill_color' => '#EF4444',
                    'norm_stroke_width' => 0.0033,
                    'norm_x' => 0.3,
                    'norm_y' => 1.45, // Vertical photo bottom region
                    'norm_width' => 0.4,
                    'norm_height' => 0.3,
                    'border_radius' => 0,
                    'dashed' => false,
                    'text' => 'Ground Cable Trench',
                    'norm_font_size' => 0.015,
                    'text_color' => '#FFFFFF',
                    'font_weight' => 'normal',
                ],
            ],
        ],
    ];

    $response = $this->actingAs($supervisor)
        ->putJson(route('sow.images.update', ['report' => $report->id, 'image' => $image->id]), $payload);

    $response->assertOk()
        ->assertJsonPath('success', true);

    $image->refresh();
    expect($image->annotation_layer['nodes'][0]['norm_y'])->toBe(1.45);
});

it('allows supervisor, manager, and admin to download SOW report PDF once published', function (): void {
    extract(sowAnnotationFixture());

    $checklistItem = $report->checklistItems()->create([
        'market_id' => $market->id,
        'label' => 'Is fiber demarc accessible?',
        'has_explain_field' => true,
        'is_required' => true,
        'sort_order' => 1,
    ]);
    $checklistItem->response()->create([
        'sow_report_id' => $report->id,
        'response' => 'yes',
        'explanation' => 'Demarc is located inside equipment shelter.',
    ]);

    $report->update(['status' => SowStatus::PUBLISHED]);

    $this->mock(BrowsershotPdfService::class, function ($mock): void {
        $mock->shouldReceive('generate')->andReturn('%PDF-1.4 Mock SOW Binary%');
    });

    $response = $this->actingAs($supervisor)
        ->get(route('sow.reports.pdf', $report->id));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($report->pdfRevisions()->count())->toBe(1);

    $managerResponse = $this->actingAs($manager)
        ->get(route('sow.reports.pdf', $report->id));
    $managerResponse->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('allows importing photos from user media library even if market_id is null or different', function (): void {
    extract(sowAnnotationFixture());

    $assetWithNullMarket = MediaAsset::factory()->create([
        'user_id' => $supervisor->id,
        'market_id' => null,
        'status' => MediaAssetStatus::READY,
    ]);

    $otherMarket = Market::factory()->create(['is_active' => true]);
    $assetWithOtherMarket = MediaAsset::factory()->create([
        'user_id' => $supervisor->id,
        'market_id' => $otherMarket->id,
        'status' => MediaAssetStatus::READY,
    ]);

    $this->actingAs($supervisor)
        ->post(route('sow.images.import-media', $report->id), [
            'media_asset_ids' => [$assetWithNullMarket->id, $assetWithOtherMarket->id],
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('imported', 2);

    expect($report->annotatedImages()->count())->toBe(3);
});

it('renders the shared site header on annotated photo pages', function (): void {
    extract(sowAnnotationFixture());

    $image->update(['include_in_pdf' => true]);
    $report->update(['site_name' => 'Columbus North Macro 5G']);

    $rendered = app(SowPdfRenderService::class)->render($report->refresh());

    expect($rendered['totalPages'])->toBe(4);

    // Pages 4+ are photo pages — each must carry the same header as the rest.
    $photoSection = substr($rendered['html'], (int) strpos($rendered['html'], 'photo-page'));

    expect($photoSection)->toContain('sow-pdf-page-header')
        ->and($photoSection)->toContain('Fiber Feasibility Report')
        ->and($photoSection)->toContain('Columbus North Macro 5G');
});

it('successfully saves empty nodes array when user removes or undos all annotations', function (): void {
    extract(sowAnnotationFixture());

    $payload = [
        'layer' => [
            'base_width' => 1200,
            'nodes' => [],
        ],
        'annotations_updated_at' => now()->format('Y-m-d H:i:s.v'),
    ];

    $response = $this->actingAs($supervisor)
        ->putJson(route('sow.images.update', ['report' => $report->id, 'image' => $image->id]), $payload);

    $response->assertOk()
        ->assertJsonPath('success', true);

    $image->refresh();
    expect($image->annotation_layer)->toBe([
        'base_width' => 1200,
        'nodes' => [],
    ])->and($image->composite_status)->toBe(CompositeStatus::NOT_NEEDED);
});
