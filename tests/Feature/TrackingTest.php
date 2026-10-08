<?php

use App\Domains\Projects\Models\Project;
use App\Domains\Tracking\Actions\UpdateLocationAction;
use App\Domains\Tracking\DTOs\LocationPingDTO;
use App\Domains\Tracking\Enums\TrackingStatus;
use App\Domains\Tracking\Models\LocationPing;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('rejects a ping with out-of-range latitude', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $tech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $project->fieldSupervisors()->attach($tech->id);

    $this->actingAs($tech)
        ->post(route('projects.tracking.ping', $project), [
            'latitude' => 91,
            'longitude' => 10,
        ])
        ->assertSessionHasErrors(['latitude']);
});

it('rejects a ping with out-of-range longitude', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $tech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $project->fieldSupervisors()->attach($tech->id);

    $this->actingAs($tech)
        ->post(route('projects.tracking.ping', $project), [
            'latitude' => 10,
            'longitude' => -181,
        ])
        ->assertSessionHasErrors(['longitude']);
});

it('creates then updates a single row per tech via upsert', function (): void {
    $tech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create();
    $project->fieldSupervisors()->attach($tech->id);
    $action = app(UpdateLocationAction::class);

    $first = $action->execute(new LocationPingDTO(
        projectId: $project->id,
        userId: $tech->id,
        latitude: 40.7128,
        longitude: -74.006,
        accuracy: 12.5,
        status: TrackingStatus::ACTIVE,
    ));

    $second = $action->execute(new LocationPingDTO(
        projectId: $project->id,
        userId: $tech->id,
        latitude: 40.7129,
        longitude: -74.0061,
        accuracy: 10.0,
        status: TrackingStatus::ACTIVE,
    ));

    expect(LocationPing::query()->where('project_id', $project->id)->where('user_id', $tech->id)->count())->toBe(1)
        ->and($first->id)->toBe($second->id)
        ->and((float) $second->refresh()->latitude)->toBe(40.7129);
});

it('records a field tech ping via the endpoint', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $tech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $project->fieldSupervisors()->attach($tech->id);

    $this->actingAs($tech)
        ->post(route('projects.tracking.ping', $project), [
            'latitude' => 40.7128,
            'longitude' => -74.006,
            'accuracy' => 15,
        ])
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    expect(LocationPing::query()->where('project_id', $project->id)->where('user_id', $tech->id)->count())->toBe(1);
});

it('denies pinging to a tech not assigned to the project', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $tech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);

    $this->actingAs($tech)
        ->post(route('projects.tracking.ping', $project), [
            'latitude' => 40.7128,
            'longitude' => -74.006,
        ])
        ->assertSessionHas('error');
});

it('lets a manager view tracking for their own project', function (): void {
    $this->withoutVite();

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $tech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $project->fieldSupervisors()->attach($tech->id);
    LocationPing::factory()->create(['project_id' => $project->id, 'user_id' => $tech->id]);

    $this->actingAs($manager)
        ->get(route('projects.tracking.show', $project))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tracking/Show')
            ->has('pings.data', 1));
});

it('denies tracking view to a manager of another project', function (): void {
    $managerA = User::factory()->create()->assignRole(Role::MANAGER->value);
    $managerB = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $managerA->id]);

    $this->actingAs($managerB)
        ->get(route('projects.tracking.show', $project))
        ->assertForbidden();
});

it('denies tracking view to field technicians', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $tech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $project->fieldSupervisors()->attach($tech->id);

    $this->actingAs($tech)
        ->get(route('projects.tracking.show', $project))
        ->assertForbidden();
});

it('returns the pings JSON contract for the polling endpoint', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $tech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $project->fieldSupervisors()->attach($tech->id);
    LocationPing::factory()->create([
        'project_id' => $project->id,
        'user_id' => $tech->id,
        'latitude' => 40.7128,
        'longitude' => -74.006,
        'status' => TrackingStatus::ACTIVE,
    ]);

    $this->actingAs($manager)
        ->getJson(route('projects.tracking.pings', $project))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user_id', $tech->id)
        ->assertJsonPath('data.0.latitude', 40.7128)
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonStructure(['data' => [['user_id', 'user_name', 'user_role', 'user_email', 'user_phone', 'user_photo_url', 'latitude', 'longitude', 'accuracy', 'status', 'pinged_at']]]);
});

it('marks pings older than the active window as offline on the endpoint', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $tech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $project->fieldSupervisors()->attach($tech->id);
    LocationPing::factory()->create([
        'project_id' => $project->id,
        'user_id' => $tech->id,
        'status' => TrackingStatus::ACTIVE,
        'pinged_at' => now()->subHours(2),
    ]);

    $this->actingAs($manager)
        ->getJson(route('projects.tracking.pings', $project))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'offline');
});

it('includes assigned technicians with offline status even when no ping was ever sent', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $tech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $project->fieldSupervisors()->attach($tech->id);

    $this->actingAs($manager)
        ->getJson(route('projects.tracking.pings', $project))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user_id', $tech->id)
        ->assertJsonPath('data.0.status', 'offline')
        ->assertJsonPath('data.0.pinged_at', null);
});

it('marks a tech offline via the beacon endpoint', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $tech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $project->fieldSupervisors()->attach($tech->id);
    LocationPing::factory()->create(['project_id' => $project->id, 'user_id' => $tech->id]);

    $this->actingAs($tech)
        ->post(route('projects.tracking.offline', $project))
        ->assertOk();

    expect(LocationPing::query()->where('project_id', $project->id)->where('user_id', $tech->id)->first()->status)
        ->toBe(TrackingStatus::OFFLINE);
});

it('opens global tracking scoped to the manager\'s first accessible project', function (): void {
    $this->withoutVite();

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $tech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $projectA = Project::factory()->create(['manager_id' => $manager->id, 'name' => 'Alpha Site']);
    Project::factory()->create(['manager_id' => $manager->id, 'name' => 'Beta Site']);
    $projectA->fieldSupervisors()->attach($tech->id);

    LocationPing::factory()->create(['project_id' => $projectA->id, 'user_id' => $tech->id]);

    $this->actingAs($manager)
        ->get(route('tracking.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tracking/Index')
            ->has('projects', 2)
            // accessibleProjects() is ordered by name, so the first one is Alpha.
            ->where('selectedProjectId', $projectA->id)
            ->has('pings.data', 1));
});

it('honours an explicit project filter on global tracking', function (): void {
    $this->withoutVite();

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $tech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    Project::factory()->create(['manager_id' => $manager->id, 'name' => 'Alpha Site']);
    $projectB = Project::factory()->create(['manager_id' => $manager->id, 'name' => 'Beta Site']);
    $projectB->fieldSupervisors()->attach($tech->id);

    LocationPing::factory()->create(['project_id' => $projectB->id, 'user_id' => $tech->id]);

    $this->actingAs($manager)
        ->get(route('tracking.index', ['project_id' => $projectB->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tracking/Index')
            ->where('selectedProjectId', $projectB->id)
            ->has('pings.data', 1));
});

it('falls back to the default project when the requested one is not accessible', function (): void {
    $this->withoutVite();

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $otherManager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $own = Project::factory()->create(['manager_id' => $manager->id, 'name' => 'Alpha Site']);
    $foreign = Project::factory()->create(['manager_id' => $otherManager->id, 'name' => 'Zulu Site']);

    $this->actingAs($manager)
        ->get(route('tracking.index', ['project_id' => $foreign->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tracking/Index')
            ->where('selectedProjectId', $own->id));
});

it('returns recent pings on the global tracking polling endpoint', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $tech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $project->fieldSupervisors()->attach($tech->id);

    LocationPing::factory()->create([
        'project_id' => $project->id,
        'user_id' => $tech->id,
        'latitude' => 41.5,
        'longitude' => -81.6,
        'status' => TrackingStatus::ACTIVE,
    ]);

    $this->actingAs($manager)
        ->getJson(route('tracking.pings'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.latitude', 41.5);
});

it('denies global tracking view to field technicians', function (): void {
    $tech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($tech)
        ->get(route('tracking.index'))
        ->assertForbidden();
});
