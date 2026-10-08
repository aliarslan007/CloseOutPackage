<?php

use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    config()->set('services.authcenter.url', 'https://authcenter.test');
    config()->set('services.redirect.url', null);
});

it('redirects to the configured url on failure', function (): void {
    config()->set('services.redirect.url', 'https://portal.example.com/login');

    Http::fake([
        '*' => Http::response(['message' => 'Forbidden'], 403),
    ]);

    $this->get(route('sso.callback').'?token=abc')
        ->assertRedirect('https://portal.example.com/login');
});

it('requires a token on callback', function (): void {
    $this->get(route('sso.callback'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error', 'SSO login failed: Missing token.');
});

it('redirects when access is denied', function (): void {
    Http::fake([
        '*' => Http::response(['message' => 'Forbidden'], 403),
    ]);

    $this->get(route('sso.callback').'?token=abc')
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error', 'Access denied: You are not authorized for this domain.');
});

it('redirects when token exchange fails', function (): void {
    Http::fake([
        '*' => Http::response(['message' => 'Bad gateway'], 502),
    ]);

    $this->get(route('sso.callback').'?token=abc')
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error', 'SSO login failed: Bad gateway');
});

it('blocks inactive users', function (): void {
    Http::fake([
        '*' => Http::response([
            'user' => ['email' => 'inactive@example.com', 'username' => 'inactive'],
        ], 200),
    ]);

    User::factory()->create([
        'email' => 'inactive@example.com',
        'is_active' => false,
    ]);

    $this->get(route('sso.callback').'?token=abc')
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error', 'Your account has been deactivated. Please contact an administrator.');

    $this->assertGuest();
});

it('creates a new user and authenticates', function (): void {
    Http::fake([
        '*' => Http::response([
            'user' => [
                'email' => 'new@example.com',
                'uuid' => 'uuid-123',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'username' => 'jdoe',
                'password' => 'remote-secret',
            ],
        ], 200),
    ]);

    $this->get(route('sso.callback').'?token=abc')
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', 'Login successful!');

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'email' => 'new@example.com',
        'uuid' => 'uuid-123',
        'name' => 'John Doe',
    ]);
});

it('updates an existing user', function (): void {
    User::factory()->create([
        'email' => 'existing@example.com',
        'name' => 'Old Name',
    ]);

    Http::fake([
        '*' => Http::response([
            'user' => [
                'email' => 'existing@example.com',
                'uuid' => 'uuid-456',
                'first_name' => 'New',
                'last_name' => 'Name',
            ],
        ], 200),
    ]);

    $this->get(route('sso.callback').'?token=abc')
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'email' => 'existing@example.com',
        'uuid' => 'uuid-456',
        'name' => 'New Name',
    ]);
});

it('does not duplicate the last name when AuthCenter sends the full name as first_name', function (): void {
    Http::fake([
        '*' => Http::response([
            'user' => [
                'email' => 'polluted@example.com',
                'uuid' => 'uuid-polluted',
                'first_name' => 'John Lime',
                'last_name' => 'Lime',
            ],
        ], 200),
    ]);

    $this->get(route('sso.callback').'?token=abc')
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('users', [
        'email' => 'polluted@example.com',
        'name' => 'John Lime',
    ]);
});

it('stops the last name from compounding across repeated logins', function (): void {
    Http::fake([
        '*' => Http::response([
            'user' => [
                'email' => 'compounding@example.com',
                'uuid' => 'uuid-compounding',
                'first_name' => 'John Lime Lime',
                'last_name' => 'Lime',
            ],
        ], 200),
    ]);

    $this->get(route('sso.callback').'?token=abc');
    $this->get(route('sso.callback').'?token=abc');

    $this->assertDatabaseHas('users', [
        'email' => 'compounding@example.com',
        'name' => 'John Lime',
    ]);
});

it('honors safe internal redirect targets', function (): void {
    Http::fake([
        '*' => Http::response([
            'user' => ['email' => 'redirect@example.com'],
        ], 200),
    ]);

    $this->get(route('sso.callback').'?token=abc&redirect_to=/dashboard')
        ->assertRedirect('/dashboard');
});

it('rejects external redirect targets', function (): void {
    Http::fake([
        '*' => Http::response([
            'user' => ['email' => 'redirect@example.com'],
        ], 200),
    ]);

    $response = $this->get(route('sso.callback').'?token=abc&redirect_to=https://evil.example.com');

    $response->assertRedirect(route('dashboard'));
    expect($response->headers->get('Location'))->not->toBe('https://evil.example.com');
});

it('routes customers to the customer portal after login', function (): void {
    User::factory()->create(['email' => 'customer@example.com'])->assignRole(Role::CUSTOMER->value);

    Http::fake([
        '*' => Http::response([
            'user' => ['email' => 'customer@example.com'],
        ], 200),
    ]);

    $this->get(route('sso.callback').'?token=abc')
        ->assertRedirect(route('portal.index'))
        ->assertSessionHas('success', 'Login successful!');
});

it('routes non-customers to the dashboard after login', function (): void {
    User::factory()->create(['email' => 'manager@example.com'])->assignRole(Role::MANAGER->value);

    Http::fake([
        '*' => Http::response([
            'user' => ['email' => 'manager@example.com'],
        ], 200),
    ]);

    $this->get(route('sso.callback').'?token=abc')
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', 'Login successful!');
});

it('assigns the field supervisor fallback role to a new user when AuthCenter omits the roles claim', function (): void {
    Http::fake([
        '*' => Http::response([
            'user' => [
                'email' => 'roleless@example.com',
                'uuid' => 'uuid-roleless',
            ],
        ], 200),
    ]);

    $this->get(route('sso.callback').'?token=abc');

    $user = User::where('email', 'roleless@example.com')->firstOrFail();

    expect($user->hasRole(Role::ADMIN->value))->toBeFalse()
        ->and($user->hasRole(Role::FIELD_SUPERVISOR->value))->toBeTrue()
        ->and($user->getRoleNames()->all())->toBe([Role::FIELD_SUPERVISOR->value]);
});

it('assigns the fallback role to an existing user with no roles at all', function (): void {
    User::factory()->create(['email' => 'existing-roleless@example.com']);

    Http::fake([
        '*' => Http::response([
            'user' => [
                'email' => 'existing-roleless@example.com',
                'uuid' => 'uuid-existing-roleless',
            ],
        ], 200),
    ]);

    $this->get(route('sso.callback').'?token=abc');

    $user = User::where('email', 'existing-roleless@example.com')->firstOrFail();

    expect($user->hasRole(Role::FIELD_SUPERVISOR->value))->toBeTrue()
        ->and($user->getRoleNames()->all())->toBe([Role::FIELD_SUPERVISOR->value]);
});

it('does not touch the role of an existing user who already has one', function (): void {
    User::factory()->create(['email' => 'has-role@example.com'])->assignRole(Role::MANAGER->value);

    Http::fake([
        '*' => Http::response([
            'user' => [
                'email' => 'has-role@example.com',
            ],
        ], 200),
    ]);

    $this->get(route('sso.callback').'?token=abc');

    $user = User::where('email', 'has-role@example.com')->firstOrFail();

    expect($user->getRoleNames()->all())->toBe([Role::MANAGER->value])
        ->and($user->hasRole(Role::FIELD_SUPERVISOR->value))->toBeFalse();
});

it('assigns the admin role to a new user when AuthCenter claims admin', function (): void {
    Http::fake([
        '*' => Http::response([
            'user' => [
                'email' => 'admin-new@example.com',
                'uuid' => 'uuid-admin-new',
                'first_name' => 'Admin',
                'last_name' => 'New',
                'roles' => 'admin',
            ],
        ], 200),
    ]);

    $this->get(route('sso.callback').'?token=abc');

    $user = User::where('email', 'admin-new@example.com')->firstOrFail();

    expect($user->hasRole(Role::ADMIN->value))->toBeTrue();
});

it('promotes an existing non-admin user to admin when AuthCenter claims admin', function (): void {
    User::factory()->create(['email' => 'promote@example.com'])->assignRole(Role::MANAGER->value);

    Http::fake([
        '*' => Http::response([
            'user' => [
                'email' => 'promote@example.com',
                'roles' => 'admin',
            ],
        ], 200),
    ]);

    $this->get(route('sso.callback').'?token=abc');

    $user = User::where('email', 'promote@example.com')->firstOrFail();

    expect($user->hasRole(Role::ADMIN->value))->toBeTrue()
        ->and($user->getRoleNames()->all())->toBe([Role::ADMIN->value]);
});

it('leaves an existing admin untouched when AuthCenter claims admin', function (): void {
    User::factory()->create(['email' => 'already-admin@example.com'])->assignRole(Role::ADMIN->value);

    Http::fake([
        '*' => Http::response([
            'user' => [
                'email' => 'already-admin@example.com',
                'roles' => 'admin',
            ],
        ], 200),
    ]);

    $this->get(route('sso.callback').'?token=abc');

    $user = User::where('email', 'already-admin@example.com')->firstOrFail();

    expect($user->getRoleNames()->all())->toBe([Role::ADMIN->value]);
});

it('does not alter roles when AuthCenter claims a non-admin role', function (): void {
    User::factory()->create(['email' => 'keeper@example.com'])->assignRole(Role::MANAGER->value);

    Http::fake([
        '*' => Http::response([
            'user' => [
                'email' => 'keeper@example.com',
                'roles' => 'manager',
            ],
        ], 200),
    ]);

    $this->get(route('sso.callback').'?token=abc');

    $user = User::where('email', 'keeper@example.com')->firstOrFail();

    expect($user->getRoleNames()->all())->toBe([Role::MANAGER->value])
        ->and($user->hasRole(Role::ADMIN->value))->toBeFalse();
});

it('handles the roles claim as an array', function (): void {
    Http::fake([
        '*' => Http::response([
            'user' => [
                'email' => 'array-roles@example.com',
                'roles' => ['staff', 'admin'],
            ],
        ], 200),
    ]);

    $this->get(route('sso.callback').'?token=abc');

    $user = User::where('email', 'array-roles@example.com')->firstOrFail();

    expect($user->hasRole(Role::ADMIN->value))->toBeTrue();
});

it('routes a user promoted from customer to the dashboard instead of the customer portal', function (): void {
    User::factory()->create(['email' => 'promoted-customer@example.com'])->assignRole(Role::CUSTOMER->value);

    Http::fake([
        '*' => Http::response([
            'user' => [
                'email' => 'promoted-customer@example.com',
                'roles' => 'admin',
            ],
        ], 200),
    ]);

    $this->get(route('sso.callback').'?token=abc')
        ->assertRedirect(route('dashboard'));
});
