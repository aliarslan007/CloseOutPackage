<?php

use App\Domains\MediaLibrary\Services\MediaQuotaService;
use App\Domains\Settings\Models\AppSetting;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\StorageQuotaExceededException;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('treats a role without a configured quota as unlimited', function (): void {
    $user = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    expect(fn () => app(MediaQuotaService::class)->assertQuota($user, 10 * 1024 * 1024 * 1024))
        ->not->toThrow(StorageQuotaExceededException::class);
});

it('enforces the configured per-role limit', function (): void {
    $user = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    AppSetting::set('media.quota.supervisor_bytes', '1000');

    $service = app(MediaQuotaService::class);

    expect(fn () => $service->assertQuota($user, 1000))->not->toThrow(StorageQuotaExceededException::class);

    expect(fn () => $service->assertQuota($user, 1001))
        ->toThrow(StorageQuotaExceededException::class, 'You have used 0 GB of your 0 GB storage limit.');
});

it('caches the used-bytes sum within the TTL', function (): void {
    $user = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    AppSetting::set('media.quota.supervisor_bytes', (string) (1024 * 1024 * 1024));

    $service = app(MediaQuotaService::class);

    DB::enableQueryLog();
    $service->assertQuota($user, 1);
    $afterFirst = count(DB::getQueryLog());

    $service->assertQuota($user, 1);
    expect(count(DB::getQueryLog()))->toBe($afterFirst);
});

it('re-queries the used-bytes sum after invalidation', function (): void {
    $user = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    AppSetting::set('media.quota.supervisor_bytes', (string) (1024 * 1024 * 1024));

    $service = app(MediaQuotaService::class);

    DB::enableQueryLog();
    $service->assertQuota($user, 1);
    $afterFirst = count(DB::getQueryLog());

    $service->invalidateUsedCache($user->id);
    $service->assertQuota($user, 1);

    expect(count(DB::getQueryLog()))->toBeGreaterThan($afterFirst);
});

it('applies the admin and manager limits from their own AppSetting keys', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    AppSetting::set('media.quota.manager_bytes', '500');

    expect(fn () => app(MediaQuotaService::class)->assertQuota($manager, 501))
        ->toThrow(StorageQuotaExceededException::class);

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    AppSetting::set('media.quota.admin_bytes', null);

    expect(fn () => app(MediaQuotaService::class)->assertQuota($admin, 10 * 1024 * 1024 * 1024))
        ->not->toThrow(StorageQuotaExceededException::class);
});

it('exposes a working flush for the role-limits cache', function (): void {
    $service = app(MediaQuotaService::class);

    Cache::put('settings.media_quota', ['field_supervisor' => '100']);
    $service->flushRoleCache();

    expect(Cache::has('settings.media_quota'))->toBeFalse();
});
