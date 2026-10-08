<?php

use App\Domains\Markets\Models\Market;
use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\Projects\Models\Project;
use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowAnnotatedImage;
use App\Domains\ScopeOfWork\Models\SowReport;
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

it('reports quota usage and per-status counts', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);

    MediaAsset::factory()->create([
        'user_id' => $fixture['field']->id,
        'market_id' => $fixture['market']->id,
        'project_id' => $fixture['project']->id,
        'status' => MediaAssetStatus::PROCESSING->value,
        'file_size_bytes' => 500,
    ]);

    $this->actingAs($fixture['field'])
        ->getJson(route('media-library.stats'))
        ->assertOk()
        ->assertJsonPath('counts.ready', 1)
        ->assertJsonPath('counts.processing', 1)
        ->assertJsonPath('counts.failed', 0)
        ->assertJsonPath('counts.total', 2)
        ->assertJsonPath('quota.used_bytes', $asset->file_size_bytes + 500)
        ->assertJsonPath('quota.limit_bytes', null); // no quota configured for the role
});

it('does not count another user\u2019s usage in stats', function (): void {
    $fixture = mediaStaffFixture();
    mediaReadyAsset($fixture);

    $other = User::factory()->create()->assignRole('field_supervisor');
    $other->markets()->attach($fixture['market']->id);

    $this->actingAs($other)
        ->getJson(route('media-library.stats'))
        ->assertOk()
        ->assertJsonPath('counts.total', 0)
        ->assertJsonPath('quota.used_bytes', 0);
});

it('reports system-wide storage usage with a per-role breakdown to admins', function (): void {
    $fixture = mediaStaffFixture();
    $fieldAsset = mediaReadyAsset($fixture);

    MediaAsset::factory()->create([
        'user_id' => $fixture['field']->id,
        'market_id' => $fixture['market']->id,
        'project_id' => $fixture['project']->id,
        'status' => MediaAssetStatus::PROCESSING->value,
        'file_size_bytes' => 100,
    ]);

    MediaAsset::factory()->create([
        'user_id' => $fixture['manager']->id,
        'market_id' => $fixture['market']->id,
        'project_id' => $fixture['project']->id,
        'status' => MediaAssetStatus::READY->value,
        'file_size_bytes' => 400,
    ]);

    $this->actingAs($fixture['admin'])
        ->getJson(route('media-library.stats'))
        ->assertOk()
        // The admin has no uploads of their own but still sees the whole system.
        ->assertJsonPath('quota.used_bytes', 0)
        ->assertJsonPath('system.total_bytes', $fieldAsset->file_size_bytes + 100 + 400)
        ->assertJsonPath('system.by_role.0.role', 'field_supervisor')
        ->assertJsonPath('system.by_role.0.used_bytes', $fieldAsset->file_size_bytes + 100)
        ->assertJsonPath('system.by_role.1.role', 'manager')
        ->assertJsonPath('system.by_role.1.used_bytes', 400)
        ->assertJsonPath('system.by_role.2.role', 'admin')
        ->assertJsonPath('system.by_role.2.used_bytes', 0);
});

it('does not expose system-wide storage usage to non-admins', function (): void {
    $fixture = mediaStaffFixture();
    mediaReadyAsset($fixture);

    $this->actingAs($fixture['field'])
        ->getJson(route('media-library.stats'))
        ->assertOk()
        ->assertJsonPath('system', null);

    $this->actingAs($fixture['manager'])
        ->getJson(route('media-library.stats'))
        ->assertOk()
        ->assertJsonPath('system', null);
});

it('re-tags an asset onto a project via PATCH', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);

    $otherProject = Project::factory()->create(['market_id' => $fixture['market']->id]);

    $this->actingAs($fixture['field'])
        ->patchJson(route('media-library.update', $asset), ['project_id' => $otherProject->id])
        ->assertOk()
        ->assertJsonPath('data.project_id', $otherProject->id);

    expect($asset->fresh()->project_id)->toBe($otherProject->id);
});

it('clears the project tag when null is sent', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);

    $this->actingAs($fixture['field'])
        ->patchJson(route('media-library.update', $asset), ['project_id' => null])
        ->assertOk()
        ->assertJsonPath('data.project_id', null);
});

it('validates the project exists before re-tagging', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);

    $this->actingAs($fixture['field'])
        ->patchJson(route('media-library.update', $asset), ['project_id' => 999_999])
        ->assertStatus(422);
});

it('lets the owner view a single asset with its relations', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);

    $this->actingAs($fixture['field'])
        ->getJson(route('media-library.show', $asset))
        ->assertOk()
        ->assertJsonPath('data.id', $asset->id)
        ->assertJsonPath('data.status', MediaAssetStatus::READY->value);
});

it('serves the original file to the owner', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);

    $this->actingAs($fixture['field'])
        ->get(route('media-library.file', $asset))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
});

it('forbids an unrelated manager from viewing a file', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);

    $outsider = User::factory()->create()->assignRole(Role::MANAGER->value);

    $this->actingAs($outsider)
        ->get(route('media-library.file', $asset))
        ->assertStatus(403);
});

it('renders the Inertia index page on a web visit with accessible projects', function (): void {
    $fixture = mediaStaffFixture();

    $this->actingAs($fixture['field'])
        ->get(route('media-library.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('MediaLibrary/Index')
            ->has('projects')
        );
});

it('serves accessible projects via /media-library/projects', function (): void {
    $fixture = mediaStaffFixture();

    $this->actingAs($fixture['field'])
        ->getJson(route('media-library.projects'))
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name']]]);
});

it('filters media assets by project_id on index', function (): void {
    $fixture = mediaStaffFixture();
    $asset1 = mediaReadyAsset($fixture);

    $project2 = Project::factory()->create([
        'market_id' => $fixture['market']->id,
        'manager_id' => $fixture['manager']->id,
    ]);
    $project2->fieldSupervisors()->attach($fixture['field']->id);

    $asset2 = MediaAsset::factory()->create([
        'user_id' => $fixture['field']->id,
        'market_id' => $fixture['market']->id,
        'project_id' => $project2->id,
        'status' => MediaAssetStatus::READY->value,
    ]);

    $this->actingAs($fixture['field'])
        ->getJson(route('media-library.index', ['project_id' => $project2->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $asset2->id);
});

it('allows a manager to view an unassigned media asset when attached to an accessible SOW report', function (): void {
    $fixture = mediaStaffFixture();

    // Create an unassigned asset belonging to the field supervisor
    $unassignedAsset = mediaReadyAsset($fixture);
    $unassignedAsset->update(['project_id' => null]);

    // An unrelated manager from another market cannot view it initially
    $otherMarket = Market::factory()->create(['is_active' => true]);
    $outsider = User::factory()->create()->assignRole(Role::MANAGER->value);
    $outsider->markets()->attach($otherMarket->id);

    $this->actingAs($outsider)
        ->get(route('media-library.file', $unassignedAsset))
        ->assertStatus(403);

    // Create SOW report for the manager's project and attach the photo
    $report = SowReport::create([
        'project_id' => $fixture['project']->id,
        'market_id' => $fixture['market']->id,
        'created_by' => $fixture['field']->id,
        'status' => SowStatus::DRAFT,
        'title' => 'Test SOW',
    ]);

    SowAnnotatedImage::create([
        'sow_report_id' => $report->id,
        'media_asset_id' => $unassignedAsset->id,
        'annotation_layer' => [],
        'sort_order' => 1,
        'include_in_pdf' => true,
    ]);

    // The manager of this SOW project can now view the file
    $this->actingAs($fixture['manager'])
        ->get(route('media-library.file', $unassignedAsset))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');

    // The unrelated outsider is still forbidden (403)
    $this->actingAs($outsider)
        ->get(route('media-library.file', $unassignedAsset))
        ->assertStatus(403);

    // Customer in market cannot view while draft
    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    $customer->markets()->attach($fixture['market']->id);
    $this->actingAs($customer)
        ->get(route('media-library.file', $unassignedAsset))
        ->assertStatus(403);

    // Customer can view once published
    $report->update(['status' => SowStatus::PUBLISHED]);
    $this->actingAs($customer)
        ->get(route('media-library.file', $unassignedAsset))
        ->assertOk();
});
