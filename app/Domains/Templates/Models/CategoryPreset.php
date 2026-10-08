<?php

declare(strict_types=1);

namespace App\Domains\Templates\Models;

use App\Domains\Users\Models\User;
use Database\Factories\CategoryPresetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A reusable subcategory heading that admins can pull into any template stage.
 * Pulling a preset copies its values into the template — it is not a reference,
 * so later edits or deletes on the preset never mutate the template.
 */
class CategoryPreset extends Model
{
    /** @use HasFactory<CategoryPresetFactory> */
    use HasFactory, LogsActivity;

    protected static string $logName = 'project';

    protected static bool $logOnlyDirty = true;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'default_picture_count',
        'is_required',
        'is_active',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_picture_count' => 'integer',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): CategoryPresetFactory
    {
        return CategoryPresetFactory::new();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'default_picture_count', 'is_required', 'is_active'])
            ->useLogName(self::$logName);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<CategoryPreset>  $query
     * @return Builder<CategoryPreset>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
