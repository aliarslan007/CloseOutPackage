<?php

declare(strict_types=1);

namespace App\Domains\Templates\Models;

use App\Domains\Templates\Enums\StageType;
use App\Shared\Traits\HasObfuscatedRouteKey;
use Database\Factories\TemplateSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TemplateSection extends Model
{
    /** @use HasFactory<TemplateSectionFactory> */
    use HasFactory, HasObfuscatedRouteKey, LogsActivity;

    protected static string $logName = 'project';

    protected static bool $logOnlyDirty = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'template_id',
        'stage',
        'heading',
        'admin_notes',
        'sort_order',
    ];

    protected static function newFactory(): TemplateSectionFactory
    {
        return TemplateSectionFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => StageType::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['heading', 'admin_notes', 'sort_order'])
            ->useLogName(self::$logName);
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return HasMany<TemplatePictureCategory, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(TemplatePictureCategory::class)->orderBy('sort_order');
    }
}
