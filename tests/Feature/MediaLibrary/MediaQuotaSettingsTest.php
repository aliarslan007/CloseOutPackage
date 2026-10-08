<?php

use App\Domains\MediaLibrary\Services\MediaQuotaService;
use App\Domains\Settings\Models\AppSetting;
use App\Domains\Settings\Services\SettingsService;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('reads persisted quota settings through the settings service', function (): void {
    AppSetting::set('media.quota.manager_bytes', '1073741824');
    AppSetting::set('media.quota.supervisor_bytes', null);

    $quotas = app(SettingsService::class)->getMediaQuotas();

    expect($quotas['media.quota.manager_bytes'])->toBe(1073741824)
        ->and($quotas['media.quota.supervisor_bytes'])->toBeNull();
});

it('setMediaQuota persists, clears and invalidates the hot-path role cache', function (): void {
    $user = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $settings = app(SettingsService::class);
    $quota = app(MediaQuotaService::class);

    // Prime the role cache with an old value.
    AppSetting::set('media.quota.supervisor_bytes', '100');
    expect($quota->limitFor($user))->toBe(100);

    $settings->setMediaQuota('supervisor', 2048);

    expect(AppSetting::get('media.quota.supervisor_bytes'))->toBe('2048')
        ->and($quota->limitFor($user))->toBe(2048); // served from the flushed cache
});

it('rejects an unknown quota role suffix', function (): void {
    expect(fn () => app(SettingsService::class)->setMediaQuota('customer', 5))
        ->toThrow(InvalidArgumentException::class);
});
