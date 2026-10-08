<?php

declare(strict_types=1);

namespace App\Domains\Templates\Models;

use App\Domains\Markets\Models\Market;
use App\Domains\Users\Models\User;
use App\Shared\Traits\HasObfuscatedRouteKey;
use Database\Factories\TemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Template extends Model implements HasMedia
{
    /** @use HasFactory<TemplateFactory> */
    use HasFactory, HasObfuscatedRouteKey, InteractsWithMedia, LogsActivity, SoftDeletes;

    public const ADDITIONAL_NOTES_COLLECTION = 'additional_site_notes';

    /** Maximum images an admin may attach; the PDF renders at most this many. */
    public const ADDITIONAL_NOTES_IMAGE_LIMIT = 6;

    protected static string $logName = 'project';

    protected static bool $logOnlyDirty = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'market_id',
        'name',
        'description',
        'additional_site_notes',
        'is_active',
        'inactive_reason',
        'created_by',
        'duplicated_from_id',
    ];

    protected static function newFactory(): TemplateFactory
    {
        return TemplateFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'additional_site_notes', 'is_active', 'market_id'])
            ->useLogName(self::$logName);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::ADDITIONAL_NOTES_COLLECTION);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Generated inline: note images are admin-uploaded at low volume, and
        // both the builder thumbnails and the PDF need them deterministically.
        $this->addMediaConversion('thumb')->width(320)->nonQueued();

        $this->addMediaConversion('pdf')->width(1400)->nonQueued();
    }

    /**
     * @return BelongsTo<Market, $this>
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    /**
     * True when the template's market is gone (soft-deleted). Such a template
     * can be kept on file but never activated until it is linked to a market.
     */
    public function marketIsMissing(): bool
    {
        return $this->market === null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<TemplateSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(TemplateSection::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<ChecklistItem, $this>
     */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<FavoriteTemplate, $this>
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(FavoriteTemplate::class);
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function duplicatedFrom(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'duplicated_from_id');
    }
}
