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

it('lists markets for admins with the users count', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.markets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Markets/Index')
            ->has('markets', 1, fn (Assert $item) => $item
                ->where('id', $market->id)
                ->where('name', $market->name)
                ->has('users_count')
                ->etc()));
});

it('only lists managers as assignable for market quick-assign', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    User::factory()->create()->assignRole(Role::CUSTOMER->value);

    $this->actingAs($admin)
        ->get(route('admin.markets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assignableUsers', 1)
            ->where('assignableUsers.0.id', $manager->id)
            ->where('assignableUsers.0.name', $manager->name));
});

it('denies market management to non-admins', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);

    $this->actingAs($manager)
        ->get(route('admin.markets.index'))
        ->assertForbidden();
});

it('requires authentication for market management', function (): void {
    $this->get(route('admin.markets.index'))->assertRedirect(route('session.expired'));
});

it('creates a market with a generated slug', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $this->actingAs($admin)
        ->post(route('admin.markets.store'), [
            'name' => 'Ohio',
            'description' => 'Ohio market description',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.markets.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('markets', [
        'name' => 'Ohio',
        'slug' => 'ohio',
        'description' => 'Ohio market description',
        'is_active' => 1,
    ]);
});

it('rejects a duplicate market name', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    Market::factory()->create(['name' => 'Ohio']);

    $this->actingAs($admin)
        ->post(route('admin.markets.store'), [
            'name' => 'Ohio',
        ])
        ->assertSessionHasErrors('name');

    expect(Market::count())->toBe(1);
});

it('allows recreating a market after soft-deletion', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    Market::factory()->create(['name' => 'Ohio']);

    $this->actingAs($admin)
        ->delete(route('admin.markets.destroy', Market::firstOrFail()))
        ->assertRedirect(route('admin.markets.index'));

    $this->actingAs($admin)
        ->post(route('admin.markets.store'), [
            'name' => 'Ohio',
        ])
        ->assertSessionHasNoErrors();
});

it('invalidates the markets cache after an update', function (): void {
    Cache::shouldReceive('supportsTags')->andReturn(true);
    $taggedCache = Mockery::mock();
    $taggedCache->shouldReceive('flush')->once();
    Cache::shouldReceive('tags')->with(['markets'])->andReturn($taggedCache);

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create(['name' => 'Ohio']);

    $this->actingAs($admin)
        ->put(route('admin.markets.update', $market), [
            'name' => 'Ohio East',
            'description' => 'Updated description',
        ])
        ->assertRedirect(route('admin.markets.index'));
});

it('updates a market and regenerates the slug on rename', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create(['name' => 'Ohio']);

    $this->actingAs($admin)
        ->put(route('admin.markets.update', $market), [
            'name' => 'Ohio East',
            'description' => 'Updated description',
            'is_active' => false,
        ])
        ->assertRedirect(route('admin.markets.index'))
        ->assertSessionHas('success');

    expect($market->fresh())
        ->name->toBe('Ohio East')
        ->slug->toBe('ohio-east')
        ->description->toBe('Updated description')
        ->is_active->toBeFalse();
});

it('rejects deleting an active market that has active projects', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create(['is_active' => true]);
    Project::factory()->create(['market_id' => $market->id]);

    $this->actingAs($admin)
        ->delete(route('admin.markets.destroy', $market))
        ->assertRedirect()
        ->assertSessionHas('error', 'This market has active projects and cannot be deleted.');

    expect(Market::find($market->id))->not->toBeNull();
});

it('rejects deleting an inactive market that has active projects', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create(['is_active' => false]);
    Project::factory()->create(['market_id' => $market->id]);

    $this->actingAs($admin)
        ->delete(route('admin.markets.destroy', $market))
        ->assertRedirect()
        ->assertSessionHas('error', 'This market has active projects and cannot be deleted.');

    expect(Market::find($market->id))->not->toBeNull();
});

it('allows deleting an active market that only has completed projects', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create(['is_active' => true]);
    Project::factory()->create(['market_id' => $market->id, 'status' => 'completed']);

    $this->actingAs($admin)
        ->delete(route('admin.markets.destroy', $market))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertSoftDeleted('markets', ['id' => $market->id]);
});

it('soft-deletes a market', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.markets.destroy', $market))
        ->assertRedirect(route('admin.markets.index'))
        ->assertSessionHas('success');

    $this->assertSoftDeleted('markets', ['id' => $market->id]);
});

it('grants market access to a user', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->from(route('admin.markets.index'))
        ->post(route('admin.markets.users.store', $market), [
            'user_id' => $user->id,
        ])
        ->assertRedirect(route('admin.markets.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('market_user', [
        'market_id' => $market->id,
        'user_id' => $user->id,
        'granted_by' => $admin->id,
    ]);
});

it('rejects granting access to a user already granted', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $user = User::factory()->create();

    $market->users()->attach($user->id);

    $this->actingAs($admin)
        ->post(route('admin.markets.users.store', $market), [
            'user_id' => $user->id,
        ])
        ->assertSessionHasErrors('user_id');
});

it('revokes market access from a user', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $user = User::factory()->create();

    $market->users()->attach($user->id, ['granted_by' => $admin->id]);

    $this->actingAs($admin)
        ->from(route('admin.markets.index'))
        ->delete(route('admin.markets.users.destroy', [$market, $user]))
        ->assertRedirect(route('admin.markets.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('market_user', [
        'market_id' => $market->id,
        'user_id' => $user->id,
    ]);
});

it('hides deactivated markets from create dropdowns but keeps them for admin management', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $active = Market::factory()->create();
    $inactive = Market::factory()->create(['is_active' => false]);

    Project::factory()->create(['market_id' => $active->id]);
    Project::factory()->create(['market_id' => $inactive->id]);

    $onlyActive = fn (Assert $item): Assert => $item->where('id', $active->id)->etc();

    // Template create / edit.
    $this->actingAs($admin)
        ->get(route('admin.templates.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Templates/Create')
            ->has('markets', 1, $onlyActive));

    // Project create and the index market filter.
    $this->get(route('projects.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Create')
            ->has('markets', 1, $onlyActive));

    $this->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('markets', 1, $onlyActive));

    // The shared Inertia prop every other dropdown reads.
    $this->get(route('projects.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('auth.markets', 1, $onlyActive));

    // Admin market management must still see the deactivated one, or it can
    // never be turned back on.
    $this->get(route('admin.markets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('markets', 2));
});
