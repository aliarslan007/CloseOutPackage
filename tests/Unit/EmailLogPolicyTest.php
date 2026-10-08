<?php

use App\Domains\Email\Policies\EmailLogPolicy;
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

it('lets admins and managers read the email audit log', function (string $role): void {
    $user = User::factory()->create()->assignRole($role);

    expect(app(EmailLogPolicy::class)->viewAny($user))->toBeTrue();
})->with([Role::ADMIN->value, Role::MANAGER->value]);

it('hides the email audit log from other roles', function (string $role): void {
    $user = User::factory()->create()->assignRole($role);

    expect(app(EmailLogPolicy::class)->viewAny($user))->toBeFalse();
})->with([Role::FIELD_SUPERVISOR->value, Role::CUSTOMER->value]);

it('restricts email log deletion to admins', function (string $role, bool $allowed): void {
    $user = User::factory()->create()->assignRole($role);

    expect(app(EmailLogPolicy::class)->delete($user))->toBe($allowed);
})->with([
    'admin' => [Role::ADMIN->value, true],
    'manager' => [Role::MANAGER->value, false],
    'field supervisor' => [Role::FIELD_SUPERVISOR->value, false],
    'customer' => [Role::CUSTOMER->value, false],
]);
