<?php

use App\Domains\Markets\Models\Market;
use App\Domains\Markets\Policies\MarketPolicy;
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

it('grants every ability to admins', function (string $ability): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $policy = app(MarketPolicy::class);

    expect($policy->{$ability}($admin, $market))->toBeTrue();
})->with([
    'viewAny', 'create', 'update', 'delete', 'grantAccess', 'revokeAccess',
]);

it('denies every ability to managers', function (string $ability): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $market = Market::factory()->create();
    $policy = app(MarketPolicy::class);

    expect($policy->{$ability}($manager, $market))->toBeFalse();
})->with([
    'viewAny', 'create', 'update', 'delete', 'grantAccess', 'revokeAccess',
]);

it('denies every ability to field supervisors', function (string $ability): void {
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $market = Market::factory()->create();
    $policy = app(MarketPolicy::class);

    expect($policy->{$ability}($field, $market))->toBeFalse();
})->with([
    'viewAny', 'create', 'update', 'delete', 'grantAccess', 'revokeAccess',
]);
