<?php

declare(strict_types=1);

namespace App\Domains\Templates\Models;

use App\Shared\Traits\HasObfuscatedRouteKey;
use Database\Factories\TemplatePictureCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TemplatePictureCategory extends Model
{
    /** @use HasFactory<TemplatePictureCategoryFactory> */
    use HasFactory, HasObfuscatedRouteKey, LogsActivity, SoftDeletes;

    protected static string $logName = 'project';

    protected static bool $logOnlyDirty = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'template_section_id',
        'category_preset_id',
        'name',
        'description',
        'default_picture_count',
        'is_required',
        'sort_order',
    ];

    protected static function newFactory(): TemplatePictureCategoryFactory
    {
        return TemplatePictureCategoryFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_picture_count' => 'integer',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'default_picture_count', 'is_required', 'sort_order'])
            ->useLogName(self::$logName);
    }

    /**
     * @return BelongsTo<TemplateSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(TemplateSection::class, 'template_section_id');
    }

    /**
     * The library preset this category was pulled from, if any.
     *
     * @return BelongsTo<CategoryPreset, $this>
     */
    public function preset(): BelongsTo
    {
        return $this->belongsTo(CategoryPreset::class, 'category_preset_id');
    }
}
