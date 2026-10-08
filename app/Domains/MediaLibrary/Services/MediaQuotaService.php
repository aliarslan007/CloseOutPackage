<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Services;

use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\Settings\Models\AppSetting;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\StorageQuotaExceededException;
use Illuminate\Support\Facades\Cache;

/**
 * Role-backed media storage quotas, cached so the upload hot path never runs
 * an uncached SUM or per-request AppSetting lookups.
 *
 * Role limits are cached for 5 minutes under one key (single fetch for all
 * roles); used bytes are cached per user for 60 seconds and invalidated on
 * every write (finalize success, delete, abort).
 */
final class MediaQuotaService
{
    private const ROLE_CACHE_KEY = 'settings.media_quota';

    private const USED_CACHE_KEY = 'media.quota.used:%d';

    private const SYSTEM_USED_CACHE_KEY = 'media.quota.used.system';

    private const ROLE_USED_CACHE_KEY = 'media.quota.used.roles';

    private const ROLE_CACHE_TTL = 300;

    private const USED_CACHE_TTL = 60;

    /**
     * Maps a Spatie role value to its AppSetting key. Field supervisors store
     * under "supervisor_bytes" to match the settings UI label.
     *
     * @var array<string, string>
     */
    private const ROLE_SETTING_KEYS = [
        Role::ADMIN->value => 'media.quota.admin_bytes',
        Role::MANAGER->value => 'media.quota.manager_bytes',
        Role::FIELD_SUPERVISOR->value => 'media.quota.supervisor_bytes',
    ];

    /**
     * Quota pre-check before accepting an upload initiate.
     *
     * @throws StorageQuotaExceededException
     */
    public function assertQuota(User $user, int $additionalBytes): void
    {
        $limit = $this->limitFor($user);

        if ($limit === null) {
            return; // Role has no quota configured — unlimited.
        }

        $used = $this->usedBytes($user);

        if (($used + $additionalBytes) > $limit) {
            throw new StorageQuotaExceededException(
                sprintf(
                    'You have used %s of your %s storage limit.',
                    $this->formatBytes($used),
                    $this->formatBytes($limit),
                ),
            );
        }
    }

    /**
     * Cached count of bytes a user's active (processing or ready, non-deleted)
     * assets currently consume.
     */
    public function usedBytes(User $user): int
    {
        return (int) Cache::remember(
            $this->usedCacheKey($user->id),
            self::USED_CACHE_TTL,
            fn (): int => $this->sumUsedBytes($user->id),
        );
    }

    /**
     * System-wide active bytes plus a per-role breakdown, so an admin who has
     * never uploaded still sees the whole platform's storage consumption.
     *
     * @return array{total_bytes: int, by_role: array<string, int>}
     */
    public function systemUsage(): array
    {
        /** @var array{total_bytes: int, by_role: array<string, int>} $usage */
        $usage = Cache::remember(self::SYSTEM_USED_CACHE_KEY, self::USED_CACHE_TTL, fn (): array => [
            'total_bytes' => $this->sumSystemUsedBytes(),
            'by_role' => $this->sumRoleUsedBytes(),
        ]);

        return $usage;
    }

    /**
     * The user's role quota in bytes, or null when their role is unlimited.
     */
    public function limitFor(User $user): ?int
    {
        $role = $user->getRoleNames()->first();
        $roleName = is_string($role) ? $role : Role::FIELD_SUPERVISOR->value;

        return $this->resolveLimitForRole($roleName);
    }

    /**
     * The quota in bytes for a role name, or null when that role is unlimited.
     */
    public function limitForRole(string $roleName): ?int
    {
        return $this->resolveLimitForRole($roleName);
    }

    /** Invalidate the cached used-bytes after finalize, delete and abort. */
    public function invalidateUsedCache(int $userId): void
    {
        Cache::forget($this->usedCacheKey($userId));
        Cache::forget(self::SYSTEM_USED_CACHE_KEY);
    }

    public function flushRoleCache(): void
    {
        Cache::forget(self::ROLE_CACHE_KEY);
    }

    private function sumUsedBytes(int $userId): int
    {
        return (int) MediaAsset::query()
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->whereIn('status', [
                MediaAssetStatus::PROCESSING->value,
                MediaAssetStatus::READY->value,
            ])
            ->sum('file_size_bytes');
    }

    private function sumSystemUsedBytes(): int
    {
        return (int) MediaAsset::query()
            ->whereNull('deleted_at')
            ->whereIn('status', [
                MediaAssetStatus::PROCESSING->value,
                MediaAssetStatus::READY->value,
            ])
            ->sum('file_size_bytes');
    }

    /**
     * Active bytes per uploader role, mirroring the single-role resolution used
     * by {@see limitFor()}. Users without one of the three quota roles are
     * counted in the system total but not in the breakdown.
     *
     * @return array<string, int>
     */
    private function sumRoleUsedBytes(): array
    {
        $usage = [
            Role::ADMIN->value => 0,
            Role::MANAGER->value => 0,
            Role::FIELD_SUPERVISOR->value => 0,
        ];

        /** @var array<int, int> $byUser */
        $byUser = MediaAsset::query()
            ->whereNull('deleted_at')
            ->whereIn('status', [
                MediaAssetStatus::PROCESSING->value,
                MediaAssetStatus::READY->value,
            ])
            ->selectRaw('user_id, SUM(file_size_bytes) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id')
            ->all();

        if ($byUser === []) {
            return $usage;
        }

        $users = User::query()
            ->whereIn('id', array_keys($byUser))
            ->with('roles:id,name')
            ->get();

        foreach ($users as $user) {
            $role = $user->getRoleNames()->first();

            if (is_string($role) && array_key_exists($role, $usage)) {
                $usage[$role] += (int) ($byUser[(int) $user->id] ?? 0);
            }
        }

        return $usage;
    }

    private function resolveLimitForRole(string $roleName): ?int
    {
        /** @var array<string, string|null> $limits */
        $limits = Cache::remember(self::ROLE_CACHE_KEY, self::ROLE_CACHE_TTL, function (): array {
            $limits = [];

            foreach (self::ROLE_SETTING_KEYS as $role => $settingKey) {
                $limits[$role] = AppSetting::get($settingKey);
            }

            return $limits;
        });

        $raw = $limits[$roleName] ?? null;

        return $raw !== null ? (int) $raw : null;
    }

    private function usedCacheKey(int $userId): string
    {
        return sprintf(self::USED_CACHE_KEY, $userId);
    }

    private function formatBytes(int $bytes): string
    {
        return round($bytes / 1_073_741_824, 1).' GB';
    }
}
