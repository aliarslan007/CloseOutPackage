<?php

use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('assigns a field supervisor to a project', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $project = Project::factory()->create(['market_id' => $market->id]);
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $field->markets()->attach($market->id);

    $this->actingAs($admin)
        ->post(route('projects.supervisors.store', $project), [
            'user_id' => $field->id,
        ])
        ->assertRedirect(route('projects.show', $project))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('project_field_supervisors', [
        'project_id' => $project->id,
        'user_id' => $field->id,
        'assigned_by' => $admin->id,
    ]);
});

it('rejects assigning a user without the field supervisor role', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);

    $this->actingAs($admin)
        ->post(route('projects.supervisors.store', $project), [
            'user_id' => $manager->id,
        ])
        ->assertSessionHasErrors('user_id');
});

it('rejects assigning a field supervisor without market access', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $project = Project::factory()->create(['market_id' => $market->id]);
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($admin)
        ->post(route('projects.supervisors.store', $project), [
            'user_id' => $field->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});

it('rejects duplicate supervisor assignments', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $project = Project::factory()->create(['market_id' => $market->id]);
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $field->markets()->attach($market->id);

    $project->fieldSupervisors()->attach($field->id);

    $this->actingAs($admin)
        ->post(route('projects.supervisors.store', $project), [
            'user_id' => $field->id,
        ])
        ->assertSessionHasErrors('user_id');
});

it('removes a field supervisor from a project', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $project = Project::factory()->create(['market_id' => $market->id]);
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $project->fieldSupervisors()->attach($field->id, ['assigned_by' => $admin->id]);

    $this->actingAs($admin)
        ->delete(route('projects.supervisors.destroy', [$project, $field]))
        ->assertRedirect(route('projects.show', $project))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('project_field_supervisors', [
        'project_id' => $project->id,
        'user_id' => $field->id,
    ]);
});

it('sets the primary field technician on a project', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project->fieldSupervisors()->attach($field->id);

    $this->actingAs($admin)
        ->put(route('projects.supervisors.primary', $project), ['user_id' => $field->id])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($project->refresh()->primary_supervisor_id)->toBe($field->id);
});

it('lets a manager set the primary field technician', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project->fieldSupervisors()->attach($field->id);

    $this->actingAs($manager)
        ->put(route('projects.supervisors.primary', $project), ['user_id' => $field->id])
        ->assertRedirect();

    expect($project->refresh()->primary_supervisor_id)->toBe($field->id);
});

it('exposes the primary technician on the project detail page', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['primary_supervisor_id' => $field->id]);
    $project->fieldSupervisors()->attach($field->id);

    $this->actingAs($admin)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('project.primary_supervisor_id', $field->id)
            ->where('project.field_supervisors.0.id', $field->id),
        );
});

it('rejects a primary technician who is not assigned to the project', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();
    $outsider = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($admin)
        ->put(route('projects.supervisors.primary', $project), ['user_id' => $outsider->id])
        ->assertSessionHasErrors('user_id');

    expect($project->refresh()->primary_supervisor_id)->toBeNull();
});

it('clears the primary field technician when null is sent', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['primary_supervisor_id' => $field->id]);
    $project->fieldSupervisors()->attach($field->id);

    $this->actingAs($admin)
        ->put(route('projects.supervisors.primary', $project), ['user_id' => null])
        ->assertRedirect();

    expect($project->refresh()->primary_supervisor_id)->toBeNull();
});

it('clears the primary technician when that supervisor is removed from the project', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['primary_supervisor_id' => $field->id]);
    $project->fieldSupervisors()->attach($field->id, ['assigned_by' => $admin->id]);

    $this->actingAs($admin)
        ->delete(route('projects.supervisors.destroy', [$project, $field]))
        ->assertRedirect(route('projects.show', $project));

    expect($project->refresh()->primary_supervisor_id)->toBeNull();
});

it('denies setting the primary technician to a field supervisor', function (): void {
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create();

    $this->actingAs($field)
        ->put(route('projects.supervisors.primary', $project), ['user_id' => $field->id])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});

it('sets the primary technician through the project update form', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $market = Market::factory()->create();
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $field->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);

    $this->actingAs($manager)
        ->put(route('projects.update', $project), [
            'name' => 'Renamed',
            'supervisor_ids' => [$field->id],
            'primary_supervisor_id' => $field->id,
        ])
        ->assertRedirect(route('projects.show', $project));

    expect($project->refresh()->primary_supervisor_id)->toBe($field->id);
});

it('clears the primary technician when the update form drops them', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['manager_id' => $manager->id, 'primary_supervisor_id' => $field->id]);
    $project->fieldSupervisors()->attach($field->id);

    $this->actingAs($manager)
        ->put(route('projects.update', $project), [
            'name' => 'Renamed',
            'supervisor_ids' => [],
        ])
        ->assertRedirect(route('projects.show', $project));

    expect($project->refresh()->primary_supervisor_id)->toBeNull();
});

it('denies supervisor assignment to field supervisors', function (): void {
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create();

    $this->actingAs($field)
        ->post(route('projects.supervisors.store', $project), [
            'user_id' => $field->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});
