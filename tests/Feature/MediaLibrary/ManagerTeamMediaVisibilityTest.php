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
 * Two managers: A runs two projects (one supervisor each), B runs a third
 * project with its own supervisor.
 *
 * @return array{market: Market, managerA: User, managerB: User, projectA1: Project, projectA2: Project, projectB1: Project, supA1: User, supA2: User, supB1: User}
 */
function managerTeamScenario(): array
{
    $market = Market::factory()->create();
    $managerA = User::factory()->create()->assignRole(Role::MANAGER->value);
    $managerB = User::factory()->create()->assignRole(Role::MANAGER->value);

    $projectA1 = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $managerA->id]);
    $projectA2 = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $managerA->id]);
    $projectB1 = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $managerB->id]);

    $supA1 = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $supA2 = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $supB1 = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $projectA1->fieldSupervisors()->attach($supA1->id);
    $projectA2->fieldSupervisors()->attach($supA2->id);
    $projectB1->fieldSupervisors()->attach($supB1->id);

    return compact('market', 'managerA', 'managerB', 'projectA1', 'projectA2', 'projectB1', 'supA1', 'supA2', 'supB1');
}

function teamAsset(User $owner, Project $project): MediaAsset
{
    return MediaAsset::factory()->create([
        'user_id' => $owner->id,
        'market_id' => $project->market_id,
        'project_id' => $project->id,
        'status' => MediaAssetStatus::READY->value,
    ]);
}

it('shows a manager their own plus assigned supervisors assets across managed projects', function (): void {
    $s = managerTeamScenario();

    $ownA = teamAsset($s['managerA'], $s['projectA1']);
    $a1 = teamAsset($s['supA1'], $s['projectA1']);
    $a2 = teamAsset($s['supA2'], $s['projectA2']);
    $b1 = teamAsset($s['supB1'], $s['projectB1']);
    $ownB = teamAsset($s['managerB'], $s['projectB1']);

    $ids = collect($this->actingAs($s['managerA'])
        ->getJson(route('media-library.index'))
        ->assertOk()
        ->json('data'))->pluck('id')->all();

    expect($ids)->toContain($ownA->id, $a1->id, $a2->id)
        ->not->toContain($b1->id, $ownB->id);
});

it('isolates the second manager to only their own team', function (): void {
    $s = managerTeamScenario();

    $a1 = teamAsset($s['supA1'], $s['projectA1']);
    $ownA = teamAsset($s['managerA'], $s['projectA1']);
    $b1 = teamAsset($s['supB1'], $s['projectB1']);
    $ownB = teamAsset($s['managerB'], $s['projectB1']);

    $ids = collect($this->actingAs($s['managerB'])
        ->getJson(route('media-library.index'))
        ->assertOk()
        ->json('data'))->pluck('id')->all();

    expect($ids)->toContain($b1->id, $ownB->id)
        ->not->toContain($a1->id, $ownA->id);
});

it('keeps a shared supervisor assets visible only on each viewing manager own project', function (): void {
    $s = managerTeamScenario();
    $shared = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $s['projectA1']->fieldSupervisors()->attach($shared->id);
    $s['projectB1']->fieldSupervisors()->attach($shared->id);

    $onA = teamAsset($shared, $s['projectA1']);
    $onB = teamAsset($shared, $s['projectB1']);

    $idsA = collect($this->actingAs($s['managerA'])
        ->getJson(route('media-library.index'))
        ->assertOk()
        ->json('data'))->pluck('id')->all();

    $idsB = collect($this->actingAs($s['managerB'])
        ->getJson(route('media-library.index'))
        ->assertOk()
        ->json('data'))->pluck('id')->all();

    expect($idsA)->toContain($onA->id)->not->toContain($onB->id);
    expect($idsB)->toContain($onB->id)->not->toContain($onA->id);
});

it('narrows team assets by project without leaking the manager own assets from elsewhere', function (): void {
    $s = managerTeamScenario();

    teamAsset($s['managerA'], $s['projectA1']);
    $a2 = teamAsset($s['supA2'], $s['projectA2']);

    $this->actingAs($s['managerA'])
        ->getJson(route('media-library.index', ['project_id' => $s['projectA2']->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $a2->id);
});

it('lets the managing manager view and delete a supervisor asset', function (): void {
    $s = managerTeamScenario();
    $asset = teamAsset($s['supA1'], $s['projectA1']);

    $this->actingAs($s['managerA'])
        ->getJson(route('media-library.show', $asset))
        ->assertOk()
        ->assertJsonPath('data.id', $asset->id);

    $this->actingAs($s['managerA'])
        ->deleteJson(route('media-library.destroy', $asset))
        ->assertNoContent();

    expect($asset->fresh()->trashed())->toBeTrue();
});

it('blocks the other manager from viewing or deleting the asset', function (): void {
    $s = managerTeamScenario();
    $asset = teamAsset($s['supA1'], $s['projectA1']);

    $this->actingAs($s['managerB'])
        ->getJson(route('media-library.show', $asset))
        ->assertStatus(403);

    $this->actingAs($s['managerB'])
        ->deleteJson(route('media-library.destroy', $asset))
        ->assertStatus(403);

    expect($asset->fresh()->trashed())->toBeFalse();
});

it('still shows a supervisor only their own assets', function (): void {
    $s = managerTeamScenario();

    $mine = teamAsset($s['supA1'], $s['projectA1']);
    teamAsset($s['supA2'], $s['projectA2']);

    $this->actingAs($s['supA1'])
        ->getJson(route('media-library.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);
});

it('counts team assets in the manager stats', function (): void {
    $s = managerTeamScenario();

    teamAsset($s['managerA'], $s['projectA1']);
    teamAsset($s['supA1'], $s['projectA1']);
    teamAsset($s['supA2'], $s['projectA2']);
    teamAsset($s['supB1'], $s['projectB1']);

    $this->actingAs($s['managerA'])
        ->getJson(route('media-library.stats'))
        ->assertOk()
        ->assertJsonPath('counts.ready', 3)
        ->assertJsonPath('counts.total', 3);
});

it('lists only team supervisors with visible counts for a manager', function (): void {
    $s = managerTeamScenario();

    teamAsset($s['supA1'], $s['projectA1']);
    teamAsset($s['supA2'], $s['projectA2']);
    teamAsset($s['supA2'], $s['projectA2']);
    teamAsset($s['supB1'], $s['projectB1']);
    teamAsset($s['managerB'], $s['projectB1']); // managers never get folders for other managers

    // Untagged uploads are invisible to the manager, so they must not count.
    MediaAsset::factory()->create([
        'user_id' => $s['supA1']->id,
        'market_id' => $s['market']->id,
        'project_id' => null,
        'status' => MediaAssetStatus::READY->value,
    ]);

    // Assigned but without uploads: no folder.
    $idle = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $s['projectA1']->fieldSupervisors()->attach($idle->id);

    $data = collect($this->actingAs($s['managerA'])
        ->getJson(route('media-library.team'))
        ->assertOk()
        ->json('data'));

    expect($data->pluck('id')->all())->toEqualCanonicalizing([$s['supA1']->id, $s['supA2']->id, $idle->id])
        ->and($data->firstWhere('id', $s['supA1']->id)['assets_count'])->toBe(2)
        ->and($data->firstWhere('id', $s['supA2']->id)['assets_count'])->toBe(2)
        ->and($data->firstWhere('id', $idle->id)['assets_count'])->toBe(0)
        ->and($data->firstWhere('id', $s['supA1']->id))->toHaveKeys(['id', 'name', 'email', 'profile_photo_url', 'assets_count']);
});

it('lists every uploader with uploads except itself for an admin', function (): void {
    $s = managerTeamScenario();

    teamAsset($s['supA1'], $s['projectA1']);
    teamAsset($s['supB1'], $s['projectB1']);
    teamAsset($s['supB1'], $s['projectB1']);
    teamAsset($s['managerB'], $s['projectB1']);

    $idle = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    teamAsset($admin, $s['projectA1']);

    $data = collect($this->actingAs($admin)
        ->getJson(route('media-library.team'))
        ->assertOk()
        ->json('data'));

    expect($data->pluck('id')->all())->toContain($s['supA1']->id, $s['supB1']->id, $s['managerB']->id, $idle->id)
        ->and($data->pluck('id')->all())->not->toContain($admin->id)
        ->and($data->firstWhere('id', $s['supB1']->id)['assets_count'])->toBe(2)
        ->and($data->firstWhere('id', $s['managerB']->id))->toHaveKeys(['id', 'name', 'email', 'role_name', 'profile_photo_url', 'assets_count']);
});

it('keeps folders for removed supervisors with visible uploads', function (): void {
    $s = managerTeamScenario();

    teamAsset($s['supA2'], $s['projectA2']);
    $s['supA2']->delete();

    $data = collect($this->actingAs($s['managerA'])
        ->getJson(route('media-library.team'))
        ->assertOk()
        ->json('data'));

    expect($data->pluck('id')->all())->toContain($s['supA2']->id)
        ->and($data->firstWhere('id', $s['supA2']->id)['assets_count'])->toBe(1);
});

it('lists managers and admins of assigned projects as folders for a supervisor', function (): void {
    $s = managerTeamScenario();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $otherManager = User::factory()->create()->assignRole(Role::MANAGER->value); // not on any of supA1's projects

    $data = collect($this->actingAs($s['supA1'])
        ->getJson(route('media-library.team'))
        ->assertOk()
        ->json('data'));

    expect($data->pluck('id')->all())->toEqualCanonicalizing([$s['managerA']->id, $admin->id])
        ->and($data->pluck('id')->all())->not->toContain($otherManager->id, $s['supA1']->id);
});

it('counts admin uploads toward the admin folder for a supervisor', function (): void {
    // Shared-visibility refactor: folder counts must include admin uploads the
    // supervisor can see (globally), not just unassigned admin uploads.
    $s = managerTeamScenario();
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    teamAsset($admin, $s['projectB1']); // project supA1 is NOT assigned to
    MediaAsset::factory()->create([
        'user_id' => $admin->id,
        'market_id' => $s['market']->id,
        'project_id' => null,
        'status' => MediaAssetStatus::READY->value,
    ]);

    $data = collect($this->actingAs($s['supA1'])
        ->getJson(route('media-library.team'))
        ->assertOk()
        ->json('data'));

    expect($data->firstWhere('id', $admin->id)['assets_count'])->toBe(2);
});

it('still shows a supervisor only their own assets in the grid', function (): void {
    $s = managerTeamScenario();

    $mine = teamAsset($s['supA1'], $s['projectA1']);
    teamAsset($s['supA2'], $s['projectA2']);

    $this->actingAs($s['supA1'])
        ->getJson(route('media-library.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);
});

it('scopes a supervisor folder to the viewing manager own projects', function (): void {
    $s = managerTeamScenario();
    $shared = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $s['projectA1']->fieldSupervisors()->attach($shared->id);
    $s['projectB1']->fieldSupervisors()->attach($shared->id);

    $visible = teamAsset($shared, $s['projectA1']);
    teamAsset($shared, $s['projectB1']);
    $untagged = MediaAsset::factory()->create([
        'user_id' => $shared->id,
        'market_id' => $s['market']->id,
        'project_id' => null,
        'status' => MediaAssetStatus::READY->value,
    ]);

    $res = $this->actingAs($s['managerA'])
        ->getJson(route('media-library.index').'?user_id='.$shared->id)
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $ids = collect($res->json('data'))->pluck('id')->all();
    expect($ids)->toContain($visible->id, $untagged->id);
});

it('returns nothing when a manager opens another team folder by id', function (): void {
    $s = managerTeamScenario();

    teamAsset($s['supB1'], $s['projectB1']);

    $this->actingAs($s['managerA'])
        ->getJson(route('media-library.index').'?user_id='.$s['supB1']->id)
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('scopes the user filter to managers of assigned projects for a supervisor', function (): void {
    $s = managerTeamScenario();

    $managerUpload = teamAsset($s['managerA'], $s['projectA1']);

    $this->actingAs($s['supA1'])
        ->getJson(route('media-library.index').'?user_id='.$s['managerA']->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $managerUpload->id);

    // A manager not on any of supA1's projects yields nothing.
    $otherManager = User::factory()->create()->assignRole(Role::MANAGER->value);
    teamAsset($otherManager, $s['projectB1']);

    $this->actingAs($s['supA1'])
        ->getJson(route('media-library.index').'?user_id='.$otherManager->id)
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('shows a technician the selected manager unassigned uploads via the generic-photos filter', function (): void {
    $s = managerTeamScenario();

    $generic = MediaAsset::factory()->create([
        'user_id' => $s['managerA']->id,
        'market_id' => $s['market']->id,
        'project_id' => null,
        'status' => MediaAssetStatus::READY->value,
    ]);

    $this->actingAs($s['supA1'])
        ->getJson(route('media-library.index', ['user_id' => $s['managerA']->id, 'unassigned' => 1]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $generic->id);
});

it('shows a technician only the selected manager uploads on the chosen project', function (): void {
    $s = managerTeamScenario();

    $onProject = teamAsset($s['managerA'], $s['projectA1']);
    MediaAsset::factory()->create([
        'user_id' => $s['managerA']->id,
        'market_id' => $s['market']->id,
        'project_id' => $s['projectA2']->id,
        'status' => MediaAssetStatus::READY->value,
    ]);

    $this->actingAs($s['supA1'])
        ->getJson(route('media-library.index', ['user_id' => $s['managerA']->id, 'project_id' => $s['projectA1']->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $onProject->id);
});

it('shows a technician every manager upload when only the manager filter is applied', function (): void {
    $s = managerTeamScenario();

    $onA1 = teamAsset($s['managerA'], $s['projectA1']);
    $generic = MediaAsset::factory()->create([
        'user_id' => $s['managerA']->id,
        'market_id' => $s['market']->id,
        'project_id' => null,
        'status' => MediaAssetStatus::READY->value,
    ]);

    $ids = collect($this->actingAs($s['supA1'])
        ->getJson(route('media-library.index', ['user_id' => $s['managerA']->id]))
        ->assertOk()
        ->json('data'))->pluck('id')->all();

    expect($ids)->toContain($onA1->id, $generic->id);
});

it('returns no team folders for a role that has none', function (): void {
    // Customers reach /media-library/team but belong to no team, so the
    // "nobody managed" branch has to return an empty list, not a staff one.
    $s = managerTeamScenario();
    teamAsset($s['supA1'], $s['projectA1']);

    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);

    $this->actingAs($customer)
        ->getJson(route('media-library.team'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
