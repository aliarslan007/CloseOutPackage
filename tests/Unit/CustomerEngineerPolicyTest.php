<?php

use App\Domains\Customers\Models\CustomerEngineer;
use App\Domains\Customers\Policies\CustomerEngineerPolicy;
use App\Domains\Markets\Models\Market;
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

it('grants update and delete to admins on live-market engineers', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $engineer = CustomerEngineer::factory()->create();
    $policy = app(CustomerEngineerPolicy::class);

    expect($policy->update($admin, $engineer))->toBeTrue()
        ->and($policy->delete($admin, $engineer))->toBeTrue();
});

it('grants update and delete to admins when the engineer region was deleted', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $engineer = CustomerEngineer::factory()->create();
    $engineer->market->delete();
    $policy = app(CustomerEngineerPolicy::class);

    expect($engineer->refresh()->market)->toBeNull()
        ->and($policy->update($admin, $engineer))->toBeTrue()
        ->and($policy->delete($admin, $engineer))->toBeTrue();
});

it('grants update and delete to managers inside their own region only', function (): void {
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $outsider = User::factory()->create()->assignRole(Role::MANAGER->value);

    $engineer = CustomerEngineer::factory()->create(['market_id' => $market->id]);
    $policy = app(CustomerEngineerPolicy::class);

    expect($policy->update($manager, $engineer))->toBeTrue()
        ->and($policy->delete($manager, $engineer))->toBeTrue()
        ->and($policy->update($outsider, $engineer))->toBeFalse()
        ->and($policy->delete($outsider, $engineer))->toBeFalse();
});

it('denies managers on engineers whose region was deleted', function (): void {
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);

    $engineer = CustomerEngineer::factory()->create(['market_id' => $market->id]);
    $market->delete();
    $policy = app(CustomerEngineerPolicy::class);

    expect($policy->update($manager, $engineer))->toBeFalse()
        ->and($policy->delete($manager, $engineer))->toBeFalse();
});
