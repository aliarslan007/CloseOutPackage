<?php

use App\Domains\Users\Jobs\SyncUserToSsoJob;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('deduplicates repeated last names and queues the AuthCenter sync', function (): void {
    Queue::fake();

    $user = User::factory()->create([
        'uuid' => fake()->uuid(),
        'email' => 'polluted@example.com',
        'name' => 'John Lime Lime',
    ])->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->artisan('users:normalize-names')
        ->expectsOutputToContain('polluted@example.com')
        ->assertSuccessful();

    expect($user->fresh()->name)->toBe('John Lime');

    Queue::assertPushed(SyncUserToSsoJob::class, function (SyncUserToSsoJob $job): bool {
        return $job->payload['first_name'] === 'John'
            && $job->payload['last_name'] === 'Lime'
            && $job->payload['full_name'] === 'John Lime';
    });
});

it('leaves clean names untouched', function (): void {
    Queue::fake();

    $user = User::factory()->create(['name' => 'John Lime']);

    $this->artisan('users:normalize-names')->assertSuccessful();

    expect($user->fresh()->name)->toBe('John Lime');
    Queue::assertNotPushed(SyncUserToSsoJob::class);
});

it('supports a dry run that does not write', function (): void {
    Queue::fake();

    $user = User::factory()->create(['name' => 'John Lime Lime']);

    $this->artisan('users:normalize-names', ['--dry-run' => true])->assertSuccessful();

    expect($user->fresh()->name)->toBe('John Lime Lime');
    Queue::assertNotPushed(SyncUserToSsoJob::class);
});
