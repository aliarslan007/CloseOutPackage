<?php

use App\Domains\Settings\Models\AppSetting;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\AppSettingSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(AppSettingSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create()->assignRole(Role::ADMIN->value);
});

it('shows the settings page to admins with media quotas and customer hierarchy', function (): void {
    $this->withoutVite();

    $this->actingAs($this->admin)
        ->get(route('admin.settings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Settings/AppSettings')
            ->has('media_quotas')
            ->has('media_config')
            ->has('customer_hierarchy'));
});

it('allows admins to update media storage quotas', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.settings.media-quotas.update'), [
            'admin_quota_gb' => null,
            'manager_quota_gb' => 15,
            'supervisor_quota_gb' => 8,
        ])
        ->assertRedirect(route('admin.settings.index'))
        ->assertSessionHas('success');

    expect(AppSetting::get('media.quota.admin_bytes'))->toBeNull()
        ->and(AppSetting::get('media.quota.manager_bytes'))->toBe((string) (15 * 1024 * 1024 * 1024))
        ->and(AppSetting::get('media.quota.supervisor_bytes'))->toBe((string) (8 * 1024 * 1024 * 1024));
});

it('shows the settings page to managers with the customer hierarchy but no admin-only settings', function (): void {
    $this->withoutVite();

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);

    $this->actingAs($manager)
        ->get(route('admin.settings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Settings/AppSettings')
            ->has('customer_hierarchy')
            // Withheld server-side, not merely hidden in the UI.
            ->missing('media_quotas')
            ->missing('media_config')
            ->missing('mail_settings'));
});

it('keeps media quota and mail writes admin-only', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);

    // A denied mutating request is redirected back with an error flash by
    // ExceptionRenderer, not rendered as a raw 403 — assert the outcome.
    $this->actingAs($manager)
        ->post(route('admin.settings.media-quotas.update'), [
            'admin_quota_gb' => 99,
            'manager_quota_gb' => 99,
            'supervisor_quota_gb' => 99,
        ])
        ->assertSessionHas('error');

    $this->actingAs($manager)
        ->post(route('admin.settings.mail.update'), ['provider' => 'brevo'])
        ->assertSessionHas('error');

    // Nothing was written.
    expect(AppSetting::get('media.quota.manager_bytes'))->not->toBe((string) (99 * 1024 * 1024 * 1024))
        ->and(AppSetting::get('mail.provider'))->not->toBe('brevo');
});

it('rejects a manager quota write sent as JSON with a 403', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);

    $this->actingAs($manager)
        ->postJson(route('admin.settings.media-quotas.update'), [
            'admin_quota_gb' => 99,
            'manager_quota_gb' => 99,
            'supervisor_quota_gb' => 99,
        ])
        ->assertForbidden();
});

it('denies the settings page to non-staff roles and guests', function (): void {
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($supervisor)
        ->get(route('admin.settings.index'))
        ->assertForbidden();
});

it('requires authentication for settings', function (): void {
    $this->get(route('admin.settings.index'))->assertRedirect(route('session.expired'));
});
