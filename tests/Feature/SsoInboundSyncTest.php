<?php

use App\Domains\Users\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('updates an existing user pushed from the SSO', function (): void {
    $user = User::factory()->create([
        'uuid' => '5f8d4a2e-9c1b-4f6a-8d3e-2b7f1a4c9e01',
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'password' => Hash::make('old-password'),
    ]);

    $this->postJson(route('sso.auth-sync'), [
        'uuid' => '5f8d4a2e-9c1b-4f6a-8d3e-2b7f1a4c9e01',
        'first_name' => 'New',
        'last_name' => 'Name',
        'email' => 'new@example.com',
        'password' => Hash::make('new-password'),
    ])->assertOk()->assertJson(['status' => true]);

    $user->refresh();

    expect($user->name)->toBe('New Name')
        ->and($user->email)->toBe('new@example.com')
        ->and(Hash::check('new-password', $user->password))->toBeTrue();
});

it('creates a user pushed from the SSO when the uuid is unknown', function (): void {
    $this->postJson(route('sso.auth-sync'), [
        'uuid' => '5f8d4a2e-9c1b-4f6a-8d3e-2b7f1a4c9e02',
        'first_name' => 'Jane',
        'last_name' => 'Supervisor',
        'email' => 'jane@example.com',
        'password' => Hash::make('secret-password'),
    ])->assertOk();

    $user = User::where('email', 'jane@example.com')->firstOrFail();

    expect($user->uuid)->toBe('5f8d4a2e-9c1b-4f6a-8d3e-2b7f1a4c9e02')
        ->and($user->name)->toBe('Jane Supervisor')
        ->and($user->is_active)->toBeTrue();
});

it('does not overwrite the local password when the SSO omits it', function (): void {
    $user = User::factory()->create([
        'uuid' => '5f8d4a2e-9c1b-4f6a-8d3e-2b7f1a4c9e03',
        'email' => 'keep@example.com',
        'password' => Hash::make('keep-password'),
    ]);

    $this->postJson(route('sso.auth-sync'), [
        'uuid' => '5f8d4a2e-9c1b-4f6a-8d3e-2b7f1a4c9e03',
        'first_name' => 'Keep',
        'last_name' => 'Me',
        'email' => 'keep@example.com',
    ])->assertOk();

    $user->refresh();

    expect(Hash::check('keep-password', $user->password))->toBeTrue();
});

it('rejects an invalid SSO push', function (): void {
    $this->postJson(route('sso.auth-sync'), [
        'uuid' => 'not-a-uuid',
        'email' => 'not-an-email',
    ])->assertUnprocessable();
});
