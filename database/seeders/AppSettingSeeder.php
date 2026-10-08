<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Settings\Models\AppSetting;
use Illuminate\Database\Seeder;

/**
 * Seeds the known settings keys (media quotas start with role defaults;
 * the admin manages the rest from Admin → Workspaces → App Settings).
 */
class AppSettingSeeder extends Seeder
{
    /**
     * @var list<array{key: string, type: string, description: string}>
     */
    private const DEFAULTS = [
        // Media Library storage quotas per role (bytes). Defaults: admin
        // unlimited, manager 10 GB, field supervisor 5 GB.
        [
            'key' => 'media.quota.admin_bytes',
            'type' => 'string',
            'description' => 'Media library storage quota for administrators in bytes (null = unlimited).',
        ],
        [
            'key' => 'media.quota.manager_bytes',
            'type' => 'string',
            'description' => 'Media library storage quota for managers in bytes (null = unlimited).',
        ],
        [
            'key' => 'media.quota.supervisor_bytes',
            'type' => 'string',
            'description' => 'Media library storage quota for field supervisors in bytes (null = unlimited).',
        ],
    ];

    /**
     * Default values for the media quota keys, keyed by AppSetting key.
     *
     * @var array<string, string|null>
     */
    private const MEDIA_QUOTA_DEFAULTS = [
        'media.quota.admin_bytes' => null,
        'media.quota.manager_bytes' => '10737418240', // 10 GB
        'media.quota.supervisor_bytes' => '5368709120', // 5 GB
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $default) {
            $value = self::MEDIA_QUOTA_DEFAULTS[$default['key']] ?? null;

            AppSetting::query()->firstOrCreate(
                ['key' => $default['key']],
                [
                    'value' => $value,
                    'type' => $default['type'],
                    'description' => $default['description'],
                ],
            );
        }
    }
}
