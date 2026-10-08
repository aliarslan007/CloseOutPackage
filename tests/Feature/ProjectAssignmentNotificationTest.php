<?php

use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Notifications\ProjectAssignedNotification;
use App\Domains\Projects\Notifications\ProjectUnassignedNotification;
use App\Domains\Projects\Notifications\SupervisorAddedToProjectNotification;
use App\Domains\Projects\Notifications\SupervisorRemovedFromProjectNotification;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('notifies a manager when a project is assigned to them', function (): void {
    Notification::fake();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => null]);

    $this->actingAs($admin)
        ->put(route('projects.update', $project), ['manager_id' => $manager->id])
        ->assertRedirect(route('projects.show', $project));

    Notification::assertSentTo($manager, ProjectAssignedNotification::class);
});

it('notifies the previous manager and the new manager on reassignment', function (): void {
    Notification::fake();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $oldManager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $newManager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $oldManager->markets()->attach($market->id);
    $newManager->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $oldManager->id]);

    $this->actingAs($admin)
        ->put(route('projects.update', $project), ['manager_id' => $newManager->id])
        ->assertRedirect(route('projects.show', $project));

    Notification::assertSentTo($oldManager, ProjectUnassignedNotification::class);
    Notification::assertSentTo($newManager, ProjectAssignedNotification::class);
    Notification::assertNotSentTo($oldManager, ProjectAssignedNotification::class);
});

it('notifies the manager when a project is created with one assigned', function (): void {
    Notification::fake();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);

    $this->actingAs($admin)
        ->post(route('projects.store'), [
            'market_id' => $market->id,
            'name' => 'Assigned Site',
            'address' => '123 Main St',
            'completed_date' => '2026-12-01',
            'awarded_date' => '2026-06-01',
            'rec_issued_date' => '2026-06-05',
            'vzw_engineer_name' => 'John Engineer',
            'vzw_engineer_email' => 'engineer@verizon.example',
            'vzw_engineer_phone' => '(555) 123-4567',
            'manager_id' => $manager->id,
        ])
        ->assertRedirect(route('projects.index'));

    Notification::assertSentTo($manager, ProjectAssignedNotification::class);
});

it('notifies the supervisor and the project manager when a supervisor is assigned', function (): void {
    Notification::fake();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $field->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);

    $this->actingAs($admin)
        ->post(route('projects.supervisors.store', $project), ['user_id' => $field->id])
        ->assertRedirect(route('projects.show', $project));

    Notification::assertSentTo($field, ProjectAssignedNotification::class);
    Notification::assertSentTo($manager, SupervisorAddedToProjectNotification::class);
});

it('notifies the supervisor and the project manager when a supervisor is removed', function (): void {
    Notification::fake();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
    $project->fieldSupervisors()->attach($field->id, ['assigned_by' => $admin->id]);

    $this->actingAs($admin)
        ->delete(route('projects.supervisors.destroy', [$project, $field]))
        ->assertRedirect(route('projects.show', $project));

    Notification::assertSentTo($field, ProjectUnassignedNotification::class);
    Notification::assertSentTo($manager, SupervisorRemovedFromProjectNotification::class);
});

it('does not notify the manager about their own supervisor addition', function (): void {
    Notification::fake();

    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $field->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);

    $this->actingAs($manager)
        ->post(route('projects.supervisors.store', $project), ['user_id' => $field->id])
        ->assertRedirect(route('projects.show', $project));

    Notification::assertSentTo($field, ProjectAssignedNotification::class);
    Notification::assertNotSentTo($manager, SupervisorAddedToProjectNotification::class);
});

it('does not notify the manager about their own supervisor removal', function (): void {
    Notification::fake();

    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
    $project->fieldSupervisors()->attach($field->id);

    $this->actingAs($manager)
        ->delete(route('projects.supervisors.destroy', [$project, $field]))
        ->assertRedirect(route('projects.show', $project));

    Notification::assertSentTo($field, ProjectUnassignedNotification::class);
    Notification::assertNotSentTo($manager, SupervisorRemovedFromProjectNotification::class);
});

it('does not notify the manager about their own supervisor change via the edit form', function (): void {
    Notification::fake();

    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $field->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);

    $this->actingAs($manager)
        ->put(route('projects.update', $project), [
            'name' => 'Renamed',
            'supervisor_ids' => [$field->id],
        ])
        ->assertRedirect(route('projects.show', $project));

    Notification::assertSentTo($field, ProjectAssignedNotification::class);
    Notification::assertNotSentTo($manager, SupervisorAddedToProjectNotification::class);
});

it('skips the team notification when the project has no manager', function (): void {
    Notification::fake();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $field->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => null]);

    $this->actingAs($admin)
        ->post(route('projects.supervisors.store', $project), ['user_id' => $field->id]);

    Notification::assertSentTo($field, ProjectAssignedNotification::class);
    Notification::assertNotSentTo($admin, SupervisorAddedToProjectNotification::class);
});

it('notifies only the affected parties when supervisors are synced on project update', function (): void {
    Notification::fake();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $kept = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $removed = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $added = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
    $project->fieldSupervisors()->attach([$kept->id, $removed->id]);

    $this->actingAs($admin)
        ->put(route('projects.update', $project), [
            'name' => 'Renamed',
            'supervisor_ids' => [$kept->id, $added->id],
        ])
        ->assertRedirect(route('projects.show', $project));

    Notification::assertSentTo($added, ProjectAssignedNotification::class);
    Notification::assertSentTo($removed, ProjectUnassignedNotification::class);
    Notification::assertSentTo($manager, SupervisorAddedToProjectNotification::class);
    Notification::assertSentTo($manager, SupervisorRemovedFromProjectNotification::class);
    Notification::assertNotSentTo($kept, ProjectAssignedNotification::class);
    Notification::assertNotSentTo($kept, ProjectUnassignedNotification::class);
});

it('shows a meaningful title and description in the notification center', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $manager = User::factory()->create(['name' => 'Casey Manager'])->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => null,
        'name' => 'Sunset Tower',
    ]);

    $this->actingAs($admin)
        ->put(route('projects.update', $project), ['manager_id' => $manager->id]);

    $this->actingAs($manager)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('items.0.title', 'Project assigned to you')
        ->assertJsonPath('items.0.description', 'Project "Sunset Tower" has been assigned to you.');
});
