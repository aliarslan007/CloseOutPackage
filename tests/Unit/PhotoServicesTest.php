<?php

use App\Domains\Photos\Policies\PhotoPolicy;
use App\Domains\Photos\Services\GpsValidationService;
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

it('keeps valid GPS coordinates', function (): void {
    $result = app(GpsValidationService::class)->normalize(40.1234567, -82.1234567, 5.5);

    expect($result['latitude'])->toBe(40.1234567)
        ->and($result['longitude'])->toBe(-82.1234567)
        ->and($result['accuracy'])->toBe(5.5);
});

it('discards out-of-range GPS coordinates', function (): void {
    $result = app(GpsValidationService::class)->normalize(95.0, -82.0, 5.5);

    expect($result['latitude'])->toBeNull()
        ->and($result['longitude'])->toBeNull()
        ->and($result['accuracy'])->toBeNull();
});

it('discards GPS when one coordinate is missing', function (): void {
    $result = app(GpsValidationService::class)->normalize(40.1, null, 5.5);

    expect($result['latitude'])->toBeNull()
        ->and($result['longitude'])->toBeNull()
        ->and($result['accuracy'])->toBeNull();
});

it('allows GPS visibility to admins, managers, and field supervisors', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    $policy = app(PhotoPolicy::class);

    expect($policy->viewGps($admin))->toBeTrue()
        ->and($policy->viewGps($manager))->toBeTrue()
        ->and($policy->viewGps($field))->toBeTrue()
        ->and($policy->viewGps($customer))->toBeFalse();
});
