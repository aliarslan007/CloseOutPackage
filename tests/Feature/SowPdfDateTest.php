<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Markets\Models\Market;
use App\Domains\PdfGeneration\Services\BrowsershotPdfService;
use App\Domains\Projects\Models\Project;
use App\Domains\ScopeOfWork\Enums\SowStatus;
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

function sowPdfDateFixture(array $attributes = []): array
{
    $market = Market::factory()->create(['is_active' => true]);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $supervisor->markets()->attach($market->id);

    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $supervisor->id,
    ]);

    $report = SowReport::create(array_merge([
        'market_id' => $market->id,
        'created_by' => $supervisor->id,
        'project_id' => $project->id,
        'status' => SowStatus::DRAFT,
        'title' => 'PDF Date SOW',
        'site_name' => 'Site Echo',
    ], $attributes));

    return compact('market', 'supervisor', 'project', 'report');
}

it('renders the current date in the footer instead of the stored revision date', function (): void {
    // Report revised long before the PDF is generated.
    extract(sowPdfDateFixture([
        'revision' => 2,
        'revision_date' => '2020-01-01',
    ]));

    $html = app(SowPdfRenderService::class)->render($report)['html'];

    $today = now()->format('m/d/Y');

    expect($html)->toContain('R2.0 '.$today)
        ->and($html)->not->toContain('01/01/2020');
});

it('renders the same footer on every page of the report', function (): void {
    // No annotated photos → the report is exactly 3 pages (cover, summary, checklist).
    extract(sowPdfDateFixture([
        'revision' => 1,
        'revision_date' => '2020-01-01',
    ]));

    $rendered = app(SowPdfRenderService::class)->render($report);
    $html = $rendered['html'];

    expect($rendered['totalPages'])->toBe(3)
        // One footer per page, each carrying the same revision label and date.
        ->and(substr_count($html, 'class="pdf-footer-wrapper"'))->toBe(3)
        ->and(substr_count($html, 'R1.0 '.now()->format('m/d/Y')))->toBe(3)
        // Every page carries the standardized proprietary footer.
        ->and($html)->toContain('Verizon Wireless Proprietary Information')
        ->and($html)->toContain('www.SoluComp.com</a>')
        ->and($html)->not->toContain('pdf-footer-simple');
});

it('generates and stores the PDF on first download and serves stored file on subsequent download', function (): void {
    extract(sowPdfDateFixture([
        'status' => SowStatus::PUBLISHED,
        'revision' => 1,
        'revision_date' => '2020-01-01',
    ]));

    // Generates once via Browsershot and persists to storage
    $this->mock(BrowsershotPdfService::class, function ($mock): void {
        $mock->shouldReceive('generate')->once()->andReturn('%PDF-1.4 Mock SOW Binary%');
    });

    $this->actingAs($supervisor)
        ->get(route('sow.reports.pdf', $report->id))
        ->assertOk();

    // Second download efficiently serves the stored PDF without re-running Browsershot
    $this->actingAs($supervisor)
        ->get(route('sow.reports.pdf', $report->id))
        ->assertOk();

    expect($report->pdfRevisions()->count())->toBe(1);
});
