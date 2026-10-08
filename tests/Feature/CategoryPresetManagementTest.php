<?php

use App\Domains\Templates\Models\CategoryPreset;
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

it('lists presets for admins with applied filters', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    CategoryPreset::factory()->create(['name' => 'Tower Foundation']);
    CategoryPreset::factory()->inactive()->create(['name' => 'Antenna Mount']);
    CategoryPreset::factory()->create(['name' => 'Cable Run']);

    $this->actingAs($admin)
        ->get(route('admin.templates.category-presets.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Templates/CategoryPresets')
            ->has('presets', 3));

    $this->actingAs($admin)
        ->get(route('admin.templates.category-presets.index', ['status' => 'active', 'search' => 'tower']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('presets', 1)
            ->where('presets.0.name', 'Tower Foundation'));
});

it('denies the library to non-admins', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);

    $this->actingAs($manager)
        ->get(route('admin.templates.category-presets.index'))
        ->assertForbidden();
});

it('requires authentication for the library', function (): void {
    $this->get(route('admin.templates.category-presets.index'))
        ->assertRedirect(route('session.expired'));
});

it('creates a preset with sensible defaults', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $this->actingAs($admin)
        ->post(route('admin.templates.category-presets.store'), [
            'name' => 'Tower Foundation',
            'description' => 'Foundation and pier detail',
            'default_picture_count' => 3,
        ])
        ->assertRedirect(route('admin.templates.category-presets.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('category_presets', [
        'name' => 'Tower Foundation',
        'description' => 'Foundation and pier detail',
        'default_picture_count' => 3,
        'is_required' => 1,
        'is_active' => 1,
        'created_by' => $admin->id,
    ]);
});

it('rejects a preset with an invalid picture count', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $this->actingAs($admin)
        ->post(route('admin.templates.category-presets.store'), [
            'name' => 'Broken',
            'default_picture_count' => 0,
        ])
        ->assertSessionHasErrors('default_picture_count');

    expect(CategoryPreset::count())->toBe(0);
});

it('updates a preset', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $preset = CategoryPreset::factory()->create([
        'name' => 'Tower Foundation',
        'default_picture_count' => 1,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.templates.category-presets.update', $preset), [
            'name' => 'Tower Foundation & Pier',
            'description' => 'Updated guidance',
            'default_picture_count' => 4,
            'is_required' => false,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.templates.category-presets.index'));

    expect($preset->fresh())
        ->name->toBe('Tower Foundation & Pier')
        ->description->toBe('Updated guidance')
        ->default_picture_count->toBe(4)
        ->is_required->toBeFalse();
});

it('toggles the active flag', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $preset = CategoryPreset::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->post(route('admin.templates.category-presets.toggle-active', $preset))
        ->assertRedirect(route('admin.templates.category-presets.index'))
        ->assertSessionHas('success', 'Subcategory deactivated.');

    expect($preset->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)
        ->post(route('admin.templates.category-presets.toggle-active', $preset))
        ->assertSessionHas('success', 'Subcategory activated.');

    expect($preset->fresh()->is_active)->toBeTrue();
});

it('deletes a preset', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $preset = CategoryPreset::factory()->create();

    $this->actingAs($admin)
        ->from(route('admin.templates.category-presets.index'))
        ->delete(route('admin.templates.category-presets.destroy', $preset))
        ->assertRedirect(route('admin.templates.category-presets.index'))
        ->assertSessionHas('success');

    expect(CategoryPreset::find($preset->id))->toBeNull();
});

it('rejects a preset with name exceeding 100 characters or description exceeding 500 characters', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $this->actingAs($admin)
        ->post(route('admin.templates.category-presets.store'), [
            'name' => str_repeat('a', 101),
            'description' => str_repeat('b', 501),
            'default_picture_count' => 1,
        ])
        ->assertSessionHasErrors(['name', 'description']);

    expect(CategoryPreset::count())->toBe(0);
});
