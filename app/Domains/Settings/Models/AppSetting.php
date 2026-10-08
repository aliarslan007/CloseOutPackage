<?php

declare(strict_types=1);

namespace App\Domains\Settings\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Global application defaults (key/value store). The ONLY place shared,
 * cross-project values live. Values are ALWAYS encrypted at rest — this table
 * exists precisely to hold sensitive defaults (PII, credentials), so there is
 * no per-row opt-out.
 *
 * To expose a new global default: add a row via a seeder/update, read it with
 * AppSetting::get(). Never create a dedicated table for one shared field.
 */
class AppSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
        ];
    }

    /**
     * Read one setting (decrypted transparently).
     */
    public static function get(string $key): ?string
    {
        /** @var self|null $setting */
        $setting = self::query()->where('key', $key)->first();

        return $setting?->value;
    }

    /**
     * Upsert one setting. Type/description are preserved when already present.
     */
    public static function set(string $key, ?string $value): void
    {
        self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }
}
