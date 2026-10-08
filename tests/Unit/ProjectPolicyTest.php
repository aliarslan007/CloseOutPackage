<?php

use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Policies\ProjectPolicy;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('allows admins to view and manage any project', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();
    $policy = app(ProjectPolicy::class);

    expect($policy->viewAny($admin))->toBeTrue()
        ->and($policy->view($admin, $project))->toBeTrue()
        ->and($policy->create($admin))->toBeTrue()
        ->and($policy->update($admin, $project))->toBeTrue()
        ->and($policy->delete($admin, $project))->toBeTrue()
        ->and($policy->assignUsers($admin, $project))->toBeTrue();
});

it('allows managers to view any project but update only assigned ones', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach(Market::factory()->create()->id);
    $assigned = Project::factory()->create(['manager_id' => $manager->id]);
    $other = Project::factory()->create();
    $policy = app(ProjectPolicy::class);

    expect($policy->viewAny($manager))->toBeTrue()
        ->and($policy->view($manager, $other))->toBeTrue()
        ->and($policy->create($manager))->toBeTrue()
        ->and($policy->update($manager, $assigned))->toBeTrue()
        ->and($policy->update($manager, $other))->toBeFalse()
        ->and($policy->delete($manager, $other))->toBeFalse()
        ->and($policy->assignUsers($manager, $other))->toBeTrue();
});

it('allows field supervisors to view only assigned projects', function (): void {
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $assigned = Project::factory()->create();
    $assigned->fieldSupervisors()->attach($field->id);
    $other = Project::factory()->create();
    $policy = app(ProjectPolicy::class);

    expect($policy->view($field, $assigned))->toBeTrue()
        ->and($policy->view($field, $other))->toBeFalse()
        ->and($policy->viewAny($field))->toBeFalse()
        ->and($policy->create($field))->toBeFalse()
        ->and($policy->delete($field, $other))->toBeFalse();
});

it('denies all project management abilities to customers', function (): void {
    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    $market = Market::factory()->create();
    $project = Project::factory()->create(['market_id' => $market->id]);
    $policy = app(ProjectPolicy::class);

    expect($policy->view($customer, $project))->toBeFalse()
        ->and($policy->create($customer))->toBeFalse()
        ->and($policy->update($customer, $project))->toBeFalse()
        ->and($policy->delete($customer, $project))->toBeFalse()
        ->and($policy->assignUsers($customer, $project))->toBeFalse();
});
