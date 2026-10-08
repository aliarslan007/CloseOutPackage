<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Markets\Models\Market;
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

function sowCoverFixture(): array
{
    $market = Market::factory()->create(['is_active' => true]);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $supervisor->markets()->attach($market->id);

    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $supervisor->id,
        'ps_number' => '17003342',
    ]);

    $report = SowReport::create([
        'market_id' => $market->id,
        'created_by' => $supervisor->id,
        'project_id' => $project->id,
        'status' => SowStatus::DRAFT,
        'title' => 'Hilton La Jolla Torrey Pines',
        'site_name' => 'Hilton La Jolla Torrey Pines',
        'report_date' => '2026-09-04',
        'fiber_provider' => 'VZB',
        'ps_number' => '17003342',
    ]);

    return compact('market', 'supervisor', 'project', 'report');
}

it('renders the client-reference cover page with centered logo, titles, photo, and two meta tables', function (): void {
    extract(sowCoverFixture());

    $html = app(SowPdfRenderService::class)->render($report)['html'];

    // Hardcoded package name and report title
    expect($html)->toContain('Fiber Feasibility Report')
        ->and($html)->toContain('Hilton La Jolla Torrey Pines')
        // SoluComp logo rendered as data URI image
        ->and($html)->toContain('alt="SoluComp Logo"')
        // Meta tables: left = site name + project id
        ->and($html)->toContain('Site Name:')
        ->and($html)->toContain('Project ID:')
        ->and($html)->toContain('17003342')
        // Meta tables: right = report date + fiber provider
        ->and($html)->toContain('Report Date:')
        ->and($html)->toContain('9/4/26')
        ->and($html)->toContain('Fiber provider:')
        ->and($html)->toContain('VZB')
        // Fifth cover block: the InSite Telecom logo renders after the tables
        ->and($html)->toContain('alt="InSite Telecom Logo"')
        ->and($html)->toContain('sow-cover-sow-logo')
        // Old single overview table is gone from the cover
        ->and($html)->not->toContain('SITE &amp; SOW OVERVIEW')
        ->and($html)->not->toContain('SITE & SOW OVERVIEW');
});

it('renders the site header on every page and keeps it unbolded off the cover', function (): void {
    extract(sowCoverFixture());

    $rendered = app(SowPdfRenderService::class)->render($report);
    $html = $rendered['html'];

    // No annotated photos on this fixture → cover, summary, checklist.
    expect($rendered['totalPages'])->toBe(3)
        // One header per page (counting rendered elements, not the CSS comment).
        ->and(substr_count($html, 'class="sow-pdf-page-header"'))->toBe(3)
        ->and(substr_count($html, '>Fiber Feasibility Report<'))->toBe(3)
        // The shared rule is unbolded; the cover's heavier weight is an override.
        ->and($html)->toMatch('/\.sow-pdf-page-header-eyebrow\s*\{[^}]*font-weight:\s*400/')
        ->and($html)->toMatch('/\.sow-pdf-page-header-title\s*\{[^}]*font-weight:\s*400/')
        ->and($html)->toMatch('/\.sow-cover-header\s+\.sow-pdf-page-header-eyebrow\s*\{[^}]*font-weight:\s*800/')
        ->and($html)->toMatch('/\.sow-cover-header\s+\.sow-pdf-page-header-title\s*\{[^}]*font-weight:\s*800/');
});

it('falls back to VZB and today date when report date and fiber provider are empty', function (): void {
    extract(sowCoverFixture());

    $report->update(['fiber_provider' => '', 'report_date' => null]);

    $html = app(SowPdfRenderService::class)->render($report->refresh())['html'];

    expect($html)->toContain('VZB')
        ->and($html)->toContain('Report Date:');
});
