<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Models;

use App\Domains\Markets\Models\Market;
use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Database\Factories\MediaAssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class MediaAsset extends Model implements HasMedia
{
    /** @use HasFactory<MediaAssetFactory> */
    use HasFactory, InteractsWithMedia {
        // Aliased so the duplicate-pointer override below can still reach the
        // real implementation — a trait method has no parent:: to call.
        getFirstMedia as protected traitGetFirstMedia;
    }

    use SoftDeletes;

    public const COLLECTION = 'media_assets';

    public const SOURCE_COLLECTION = 'source_media';

    /**
     * Virtual attributes appended to every serialized model.
     *
     * @var list<string>
     */
    protected $appends = ['url', 'original_url', 'thumb_url', 'source_image_url'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'market_id',
        'project_id',
        'source_asset_id',
        'annotation_layer',
        'original_filename',
        'mime_type',
        'file_size_bytes',
        'total_chunks',
        'gps_latitude',
        'gps_longitude',
        'gps_accuracy',
        'captured_at',
        'status',
        'validation_error',
        'file_hash',
        'duplicate_of_asset_id',
    ];

    protected static function newFactory(): MediaAssetFactory
    {
        return MediaAssetFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MediaAssetStatus::class,
            'source_asset_id' => 'integer',
            'duplicate_of_asset_id' => 'integer',
            'annotation_layer' => 'array',
            'file_size_bytes' => 'integer',
            'total_chunks' => 'integer',
            'gps_latitude' => 'float',
            'gps_longitude' => 'float',
            'gps_accuracy' => 'float',
            'captured_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COLLECTION)->singleFile();
        $this->addMediaCollection(self::SOURCE_COLLECTION)->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections(self::COLLECTION)
            ->width(1000)
            ->height(1000)
            ->sharpen(10);

        $this->addMediaConversion('medium')
            ->performOnCollections(self::COLLECTION)
            ->width(2000)
            ->height(1500);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->owner();
    }

    /**
     * @return BelongsTo<Market, $this>
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Photos created from this asset via the COP assign flow.
     *
     * @return BelongsToMany<Photo, $this>
     */
    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Photo::class, 'media_asset_photos')
            ->withTimestamps();
    }

    /**
     * The original unannotated asset this asset was derived from (if any).
     *
     * @return BelongsTo<self, $this>
     */
    public function sourceAsset(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_asset_id')->withTrashed();
    }

    /**
     * The asset that already held these exact bytes when this one was
     * processed. Non-null means this row is a pointer: it owns no file.
     *
     * @return BelongsTo<self, $this>
     */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_asset_id')->withTrashed();
    }

    /**
     * The asset that physically holds the bytes — this one, unless this row is
     * a duplicate pointer, in which case the canonical asset it defers to.
     * Null when this is a pointer whose canonical row is gone: it then owns no
     * bytes of its own and nothing can be resolved through it.
     */
    public function canonical(): ?self
    {
        if ($this->duplicate_of_asset_id === null) {
            return $this;
        }

        $canonical = $this->relationLoaded('duplicateOf')
            ? $this->duplicateOf
            : $this->duplicateOf()->first();

        return $canonical instanceof self ? $canonical : null;
    }

    /**
     * Resolve file access through duplicate pointers so a pointer behaves
     * exactly like the asset holding the bytes. This is the single seam every
     * reader already goes through (PDF render, file controller, photo copy),
     * so no caller needs to know pointers exist.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getFirstMedia(string $collectionName = 'default', $filters = []): ?Media
    {
        return $this->traitGetFirstMedia($collectionName, $filters)
            ?? $this->canonical()?->getFirstMedia($collectionName, $filters);
    }

    public function getUrlAttribute(): ?string
    {
        return $this->exists && $this->status === MediaAssetStatus::READY
            ? $this->fileUrl()
            : null;
    }

    public function getOriginalUrlAttribute(): ?string
    {
        return $this->getUrlAttribute();
    }

    /**
     * Lightweight 1000px thumbnail URL used in dense grids and the picker.
     */
    public function getThumbUrlAttribute(): ?string
    {
        return $this->exists && $this->status === MediaAssetStatus::READY
            ? $this->fileUrl('thumb')
            : null;
    }

    /**
     * Returns the clean, unannotated base image URL.
     *
     * If this asset was derived from a source asset, returns the root source asset's URL.
     * If this asset was overwritten and has a preserved clean copy in source_media, returns that file's URL.
     * Otherwise, returns this asset's own URL.
     */
    public function getSourceImageUrlAttribute(): ?string
    {
        if (! $this->exists || $this->status !== MediaAssetStatus::READY) {
            return null;
        }

        if ($this->source_asset_id !== null) {
            $source = $this->relationLoaded('sourceAsset')
                ? $this->sourceAsset
                : $this->sourceAsset()->first();

            if ($source !== null && $source->url !== null) {
                return $source->url;
            }
        }

        if ($this->hasMedia(self::SOURCE_COLLECTION)) {
            $params = ['asset' => $this, 'source' => 1];
            if ($this->updated_at !== null) {
                $params['v'] = $this->updated_at->timestamp;
            }

            return route('media-library.file', $params);
        }

        return $this->getUrlAttribute();
    }

    private function fileUrl(?string $conversion = null): string
    {
        $params = ['asset' => $this];

        if ($conversion !== null) {
            $params['conversion'] = $conversion;
        }

        if ($this->updated_at !== null) {
            $params['v'] = $this->updated_at->timestamp;
        }

        return route('media-library.file', $params);
    }
}
