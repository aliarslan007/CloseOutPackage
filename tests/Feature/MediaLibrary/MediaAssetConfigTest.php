<?php

use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('serves the upload config straight from config/cop.php', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $response = $this->actingAs($admin)
        ->getJson(route('media-library.config'))
        ->assertOk();

    expect($response->json('allowed_mimes'))->toBe(config('cop.media_allowed_mimes'))
        ->and($response->json('max_file_bytes'))
        ->toBe(((int) config('cop.media_max_file_mb')) * 1024 * 1024)
        ->and($response->json('chunk_size_bytes'))
        ->toBe(((int) config('cop.media_chunk_size_mb')) * 1024 * 1024);
});

it('reflects overridden values from the environment', function (): void {
    config()->set('cop.media_max_file_mb', 2);
    config()->set('cop.media_chunk_size_mb', 1);

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $this->actingAs($admin)
        ->getJson(route('media-library.config'))
        ->assertOk()
        ->assertJson([
            'max_file_bytes' => 2 * 1024 * 1024,
            'chunk_size_bytes' => 1 * 1024 * 1024,
        ]);
});
