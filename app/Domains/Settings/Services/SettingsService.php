<?php

declare(strict_types=1);

namespace App\Domains\Settings\Services;

use App\Domains\MediaLibrary\Services\MediaQuotaService;
use App\Domains\Settings\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Single source of truth for reading/writing global defaults, with caching so
 * hot paths (project create form) don't decrypt on every render.
 */
final class SettingsService
{
    private const MEDIA_QUOTA_CACHE_KEY = 'settings.media_quota_values';

    private const CACHE_TTL_SECONDS = 300;

    /**
     * Keys backing the Media Library per-role storage quotas. Value is a byte
     * count as a string, or null when the role is unlimited.
     *
     * @var list<string>
     */
    public const MEDIA_QUOTA_KEYS = [
        'media.quota.admin_bytes',
        'media.quota.manager_bytes',
        'media.quota.supervisor_bytes',
    ];

    /**
     * All three media quota settings in bytes (null = unlimited), cached so
     * the admin page never decrypts per render.
     *
     * @return array<string, int|null> keyed by media.quota.*_bytes setting key
     */
    public function getMediaQuotas(): array
    {
        /** @var array<string, int|null> $quotas */
        $quotas = Cache::remember(self::MEDIA_QUOTA_CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            $quotas = [];

            foreach (self::MEDIA_QUOTA_KEYS as $key) {
                $raw = AppSetting::get($key);
                $quotas[$key] = $raw !== null ? (int) $raw : null;
            }

            return $quotas;
        });

        return $quotas;
    }

    /**
     * Persist one role quota (null clears it — unlimited). The role is the
     * settings-key suffix (admin|manager|supervisor), not the Spatie role
     * value; MediaQuotaService maps the enum value to the same suffix.
     *
     * Flushes both this service's cache and the MediaQuotaService role cache
     * so the upload hot path picks the new limit up immediately.
     */
    public function setMediaQuota(string $role, ?int $bytes): void
    {
        $settingKey = "media.quota.{$role}_bytes";

        if (! in_array($settingKey, self::MEDIA_QUOTA_KEYS, true)) {
            throw new \InvalidArgumentException("Unknown media quota role [{$role}].");
        }

        AppSetting::set($settingKey, $bytes !== null ? (string) $bytes : null);

        $this->flushMediaQuotaCache();
        app(MediaQuotaService::class)->flushRoleCache();
    }

    public function flushMediaQuotaCache(): void
    {
        Cache::forget(self::MEDIA_QUOTA_CACHE_KEY);
    }
}
