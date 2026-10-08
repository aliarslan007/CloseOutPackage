<?php

use App\Domains\Markets\Models\Market;
use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\Projects\Models\Project;
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
 * A ready asset owned by $owner, tagged to $project (or market-only).
 */
function mediaAssetFor(User $owner, ?Project $project = null, ?Market $market = null): MediaAsset
{
    return MediaAsset::factory()->create([
        'user_id' => $owner->id,
        'market_id' => $project?->market_id ?? $market?->id,
        'project_id' => $project?->id,
        'status' => MediaAssetStatus::READY->value,
    ]);
}

it('shows a supervisor their own plus shared-project assets, not other technicians untagged uploads', function (): void {
    $fixture = mediaStaffFixture();
    $other = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $mine = mediaAssetFor($fixture['field'], $fixture['project']);
    $theirs = mediaAssetFor($other, $fixture['project']);

    $this->actingAs($fixture['field'])
        ->getJson(route('media-library.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $ids = collect($this->actingAs($fixture['field'])
        ->getJson(route('media-library.index'))
        ->json('data'))->pluck('id')->all();

    expect($ids)->toContain($mine->id, $theirs->id);

    // An untagged upload by the other technician stays invisible.
    $untagged = mediaAssetFor($other, null, $fixture['market']);

    $ids = collect($this->actingAs($fixture['field'])
        ->getJson(route('media-library.index'))
        ->json('data'))->pluck('id')->all();

    expect($ids)->not->toContain($untagged->id);
});

it('lets an admin see all assets and filter by owner', function (): void {
    $fixture = mediaStaffFixture();
    $supervisorB = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    mediaAssetFor($fixture['field'], $fixture['project']);
    mediaAssetFor($supervisorB, $fixture['project']);

    $this->actingAs($fixture['admin'])
        ->getJson(route('media-library.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->actingAs($fixture['admin'])
        ->getJson(route('media-library.index').'?user_id='.$supervisorB->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user_id', $supervisorB->id);
});

it('shows an admin upload on another project to a field supervisor', function (): void {
    // Product rule: admin uploads are globally visible to staff, even when
    // tagged to a project the supervisor is not assigned to.
    $fixture = mediaStaffFixture();
    $otherProject = Project::factory()->create(['market_id' => $fixture['market']->id]);

    $adminUpload = mediaAssetFor($fixture['admin'], $otherProject);

    $this->actingAs($fixture['field'])
        ->getJson(route('media-library.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $adminUpload->id);
});

it('filters the picker by uploader', function (): void {
    $fixture = mediaStaffFixture();
    $other = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    mediaAssetFor($fixture['field'], $fixture['project']);
    $otherUpload = mediaAssetFor($other, $fixture['project']);

    $this->actingAs($fixture['admin'])
        ->getJson(route('media-library.picker').'?scope=all&user_id='.$other->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $otherUpload->id);
});

it('excludes non-ready assets from the grid', function (): void {
    $fixture = mediaStaffFixture();

    $ready = mediaAssetFor($fixture['field'], $fixture['project']);
    MediaAsset::factory()->create([
        'user_id' => $fixture['field']->id,
        'market_id' => $fixture['market']->id,
        'project_id' => $fixture['project']->id,
        'status' => MediaAssetStatus::PROCESSING->value,
    ]);

    $this->actingAs($fixture['field'])
        ->getJson(route('media-library.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ready->id);
});

it('scopes the picker to the context project by default', function (): void {
    $fixture = mediaStaffFixture();
    $otherProject = Project::factory()->create(['market_id' => $fixture['market']->id]);

    $inContext = mediaAssetFor($fixture['field'], $fixture['project']);
    mediaAssetFor($fixture['field'], $otherProject);

    $this->actingAs($fixture['field'])
        ->getJson(route('media-library.picker').'?project_id='.$fixture['project']->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $inContext->id);
});

it('lets a manager switch the picker to any other project they manage', function (): void {
    // Change orders are written against one project, but the manager picks
    // their supporting photos from the whole library: every managed project
    // is offered as a filter, and the picker honours the one they land on.
    $fixture = mediaStaffFixture();
    $managedProject = Project::factory()->create([
        'market_id' => $fixture['market']->id,
        'manager_id' => $fixture['manager']->id,
    ]);

    $inContext = mediaAssetFor($fixture['field'], $fixture['project']);
    $inManagedProject = mediaAssetFor($fixture['field'], $managedProject);

    $offered = collect($this->actingAs($fixture['manager'])
        ->getJson(route('media-library.projects'))
        ->assertOk()
        ->json('data'))->pluck('id')->all();

    expect($offered)->toContain($fixture['project']->id, $managedProject->id);

    $this->actingAs($fixture['manager'])
        ->getJson(route('media-library.picker').'?project_id='.$managedProject->id.'&scope=project')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $inManagedProject->id);

    // The change order's own project is still filterable.
    $this->actingAs($fixture['manager'])
        ->getJson(route('media-library.picker').'?project_id='.$fixture['project']->id.'&scope=project')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $inContext->id);
});

it('scopes the picker across the whole market when asked', function (): void {
    $fixture = mediaStaffFixture();
    $otherProject = Project::factory()->create(['market_id' => $fixture['market']->id]);
    $otherMarketProject = Project::factory()->create(); // separate market

    $a = mediaAssetFor($fixture['field'], $fixture['project']);
    $b = mediaAssetFor($fixture['field'], $otherProject);
    mediaAssetFor($fixture['field'], $otherMarketProject);

    $response = $this->actingAs($fixture['field'])
        ->getJson(route('media-library.picker').'?project_id='.$fixture['project']->id.'&scope=market');

    $response->assertOk()->assertJsonCount(2, 'data');

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($a->id, $b->id)->not->toContain($otherMarketProject->id);
});

it('filters the picker by search and market', function (): void {
    $fixture = mediaStaffFixture();
    $otherProject = Project::factory()->create(['market_id' => $fixture['market']->id]);

    mediaAssetFor($fixture['field'], $fixture['project']);
    mediaAssetFor($fixture['field'], $otherProject);

    $target = MediaAsset::factory()->create([
        'user_id' => $fixture['field']->id,
        'market_id' => $fixture['market']->id,
        'project_id' => $fixture['project']->id,
        'status' => MediaAssetStatus::READY->value,
        'original_filename' => 'foundation-slab-42.jpg',
    ]);

    $this->actingAs($fixture['field'])
        ->getJson(route('media-library.index').'?search=foundation-slab')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $target->id);
});

it('filters uploads not tagged to any project via unassigned', function (): void {
    $fixture = mediaStaffFixture();

    mediaAssetFor($fixture['field'], $fixture['project']);
    $untagged = mediaAssetFor($fixture['field'], null, $fixture['market']);

    $this->actingAs($fixture['field'])
        ->getJson(route('media-library.index').'?unassigned=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $untagged->id);
});

it('shows a manager their own and assigned supervisors untagged uploads under unassigned', function (): void {
    $fixture = mediaStaffFixture();

    $own = mediaAssetFor($fixture['manager'], null, $fixture['market']);
    $fieldAsset = mediaAssetFor($fixture['field'], null, $fixture['market']);

    $ids = collect($this->actingAs($fixture['manager'])
        ->getJson(route('media-library.index').'?unassigned=1')
        ->assertOk()
        ->json('data'))->pluck('id')->all();

    expect($ids)->toContain($own->id, $fieldAsset->id);
});

it('lists only untagged uploads in the picker when generic photos are asked for', function (): void {
    // The dropdown offers "Generic Photos" so a change order or SOW can pull in
    // library-wide photos that belong to no project. `scope` stays a valid enum
    // member (project|market|all) — the UI spells "generic" as all + unassigned.
    $fixture = mediaStaffFixture();

    mediaAssetFor($fixture['field'], $fixture['project']);
    $untagged = mediaAssetFor($fixture['field'], null, $fixture['market']);

    $this->actingAs($fixture['field'])
        ->getJson(route('media-library.picker').'?project_id='.$fixture['project']->id.'&scope=all&unassigned=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $untagged->id);
});

it('rejects a picker scope outside the supported enum', function (): void {
    $fixture = mediaStaffFixture();

    // Guards the dropdown's vocabulary from leaking an unsupported scope value.
    $this->actingAs($fixture['field'])
        ->getJson(route('media-library.picker').'?project_id='.$fixture['project']->id.'&scope=generic')
        ->assertStatus(422)
        ->assertJsonValidationErrors('scope');
});

it('excludes assets deleted via the trash', function (): void {
    $fixture = mediaStaffFixture();

    $asset = mediaAssetFor($fixture['field'], $fixture['project']);
    $asset->delete();

    $this->actingAs($fixture['field'])
        ->getJson(route('media-library.index'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('filters assets by status accurately when status filter is provided', function (): void {
    $fixture = mediaStaffFixture();

    $ready = mediaAssetFor($fixture['field'], $fixture['project']);
    $processing = MediaAsset::factory()->create([
        'user_id' => $fixture['field']->id,
        'market_id' => $fixture['market']->id,
        'project_id' => $fixture['project']->id,
        'status' => MediaAssetStatus::PROCESSING->value,
    ]);
    $failed = MediaAsset::factory()->create([
        'user_id' => $fixture['field']->id,
        'market_id' => $fixture['market']->id,
        'project_id' => $fixture['project']->id,
        'status' => MediaAssetStatus::FAILED->value,
    ]);

    // status=all should return all 3 assets
    $this->actingAs($fixture['field'])
        ->getJson(route('media-library.index').'?status=all')
        ->assertOk()
        ->assertJsonCount(3, 'data');

    // status=processing should only return the processing asset
    $this->actingAs($fixture['field'])
        ->getJson(route('media-library.index').'?status=processing')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $processing->id);

    // status=failed should only return the failed asset
    $this->actingAs($fixture['field'])
        ->getJson(route('media-library.index').'?status=failed')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $failed->id);

    // status=ready should only return the ready asset
    $this->actingAs($fixture['field'])
        ->getJson(route('media-library.index').'?status=ready')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ready->id);
});

it('limits the SOW picker shape to visible ready in-market assets', function (): void {
    // Mirrors the SOW import dialog params: scope=all + market_id, no project.
    // Explains "47 in the library, 25 in the dialog": other markets,
    // non-ready uploads, other owners and untagged uploads stay out.
    $fixture = mediaStaffFixture();
    $otherMarket = Market::factory()->create();
    $other = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $shown = mediaAssetFor($fixture['field'], $fixture['project']);

    MediaAsset::factory()->create([
        'user_id' => $fixture['field']->id,
        'market_id' => $otherMarket->id,
        'project_id' => null,
        'status' => MediaAssetStatus::READY->value,
    ]);
    MediaAsset::factory()->create([
        'user_id' => $fixture['field']->id,
        'market_id' => $fixture['market']->id,
        'project_id' => $fixture['project']->id,
        'status' => MediaAssetStatus::PROCESSING->value,
    ]);
    mediaAssetFor($other, $fixture['project']);
    MediaAsset::factory()->create([
        'user_id' => $fixture['field']->id,
        'market_id' => null,
        'project_id' => null,
        'status' => MediaAssetStatus::READY->value,
    ]);

    $this->actingAs($fixture['field'])
        ->getJson(route('media-library.picker').'?scope=all&market_id='.$fixture['market']->id)
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $ids = collect($this->actingAs($fixture['field'])
        ->getJson(route('media-library.picker').'?scope=all&market_id='.$fixture['market']->id)
        ->json('data'))->pluck('id')->all();

    // Shared-project asset from the other technician is now visible; the
    // field technician's other-market and untagged uploads stay out.
    expect($ids)->toContain($shown->id);
    expect(count($ids))->toBe(2);
});
