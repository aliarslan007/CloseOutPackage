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

function sowSummaryFixture(array $attributes = []): SowReport
{
    $market = Market::factory()->create(['is_active' => true]);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $supervisor->markets()->attach($market->id);

    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $supervisor->id,
    ]);

    return SowReport::create(array_merge([
        'market_id' => $market->id,
        'created_by' => $supervisor->id,
        'project_id' => $project->id,
        'status' => SowStatus::DRAFT,
        'title' => 'Summary Layout SOW',
        'site_name' => 'Site Delta',
        'demarcation' => 'Equipment Shelter Rack #4',
        'scope_of_work_description' => 'Detailed scope of work description text.',
        'revision' => 1,
        'revision_date' => now()->toDateString(),
    ], $attributes));
}

it('renders demarcation and scope of work as their own full-width rows', function (): void {
    $report = sowSummaryFixture();

    $html = app(SowPdfRenderService::class)->render($report)['html'];

    // Demarcation: full-width label + value row.
    expect($html)->toMatch('/Demarcation:<\/td>\s*<td class="summary-value" colspan="3">Equipment Shelter Rack #4<\/td>/')
        ->and($html)->toContain('Equipment Shelter Rack #4')
        // Scope of work sits below demarcation and keeps the full width.
        ->and($html)->toMatch('/Scope of work:<\/td>\s*<td class="summary-value" colspan="3">/')
        ->and($html)->toContain('Detailed scope of work description text.')
        // The old merged-cell / filler approach is gone.
        ->and($html)->not->toContain('rowspan')
        ->and($html)->not->toContain('summary-filler-black');
});

it('renders a long scope of work in a single full-width cell', function (): void {
    $longScope = str_repeat('Comprehensive scope detail. ', 20);

    $report = sowSummaryFixture(['scope_of_work_description' => $longScope]);

    $html = app(SowPdfRenderService::class)->render($report)['html'];

    expect($html)->toMatch('/Scope of work:<\/td>\s*<td class="summary-value" colspan="3">/')
        ->and($html)->toContain(trim($longScope));
});

it('keeps the demarcation row compact when both fields are long', function (): void {
    $report = sowSummaryFixture([
        'demarcation' => str_repeat('D', 100),
        'scope_of_work_description' => str_repeat('S', 400),
    ]);

    $html = app(SowPdfRenderService::class)->render($report)['html'];

    // Demarcation never grows into a merged tall cell.
    expect($html)->toMatch('/Demarcation:<\/td>\s*<td class="summary-value" colspan="3">'.str_repeat('D', 100).'<\/td>/')
        ->and($html)->not->toContain('rowspan');
});
