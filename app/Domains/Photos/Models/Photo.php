<?php

declare(strict_types=1);

namespace App\Domains\Photos\Models;

use App\Domains\Approval\Models\PhotoApproval;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Users\Models\User;
use App\Shared\Traits\HasObfuscatedRouteKey;
use Database\Factories\PhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Photo extends Model implements HasMedia
{
    /** @use HasFactory<PhotoFactory> */
    use HasFactory, HasObfuscatedRouteKey, InteractsWithMedia, LogsActivity;

    public const COLLECTION = 'photos';

    protected static string $logName = 'media';

    protected static bool $logOnlyDirty = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'closeout_package_id',
        'package_stage_id',
        'package_category_selection_id',
        'uploaded_by',
        'position',
        'notes',
        'status',
        'gps_latitude',
        'gps_longitude',
        'gps_accuracy',
        'captured_at',
        'flagged_at',
        'flagged_by',
        'flag_reason',
        'replaced_at',
        'replaced_by_photo_id',
        'is_approved',
        'approved_at',
        'approved_by',
    ];

    protected static function newFactory(): PhotoFactory
    {
        return PhotoFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PhotoStatus::class,
            'position' => 'integer',
            'gps_latitude' => 'float',
            'gps_longitude' => 'float',
            'gps_accuracy' => 'float',
            'captured_at' => 'datetime',
            'flagged_at' => 'datetime',
            'replaced_at' => 'datetime',
            'is_approved' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'position', 'is_approved'])
            ->useLogName(self::$logName);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COLLECTION)->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(1000)
            ->height(1000)
            ->sharpen(10);

        $this->addMediaConversion('medium')
            ->width(2000)
            ->height(1500);

        // Print-size derivative consumed by the PDF pipeline. Queued by
        // default (config/media-library.php: queue_conversions_by_default),
        // so generation-time GD work moves to upload time. Dimensions and
        // quality mirror PdfPhotoOptimizer (1200px / JPEG q80) so the
        // pre-generated file is pixel-equivalent to the on-the-fly copy.
        // Stored as JPEG: HEIC originals are normalized to JPEG at upload
        // time (PhotoStorageService), and the pipeline feeds Chromium local
        // file paths, which cannot decode HEIC.
        // NOTE: no performOnQueue() call on purpose — conversions must stay
        // on the default conversions queue (MEDIA_QUEUE / `default`), never
        // on the `pdf` render queue, so a manager pull of generate-PDF load
        // can never starve thumbnail generation, and vice versa.
        $this->addMediaConversion('pdf')
            ->width(1200)
            ->height(1200)
            ->quality(80);
    }

    /**
     * @return BelongsTo<CloseoutPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(CloseoutPackage::class, 'closeout_package_id');
    }

    /**
     * @return BelongsTo<PackageStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(PackageStage::class, 'package_stage_id');
    }

    /**
     * @var list<string>
     */
    protected $appends = ['url', 'original_url', 'thumb_url'];

    public function getUrlAttribute(): ?string
    {
        return $this->signedUrl();
    }

    public function getOriginalUrlAttribute(): ?string
    {
        return $this->getUrlAttribute();
    }

    /**
     * Lightweight 300px thumbnail URL used in dense grids, so large packages
     * with hundreds of photos stay fast on mobile and desktop.
     */
    public function getThumbUrlAttribute(): ?string
    {
        return $this->signedUrl('thumb');
    }

    private function signedUrl(?string $conversion = null): ?string
    {
        if (! $this->exists || ! $this->closeout_package_id) {
            return null;
        }

        $url = route('photos.signed-url', [
            'package' => $this->closeout_package_id,
            'photo' => $this->id,
        ]);

        return $conversion !== null ? $url.'?conversion='.$conversion : $url;
    }

    /**
     * @return BelongsTo<PackageCategorySelection, $this>
     */
    public function categorySelection(): BelongsTo
    {
        return $this->belongsTo(PackageCategorySelection::class, 'package_category_selection_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function flagger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'flagged_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<Photo, $this>
     */
    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(Photo::class, 'replaced_by_photo_id');
    }

    /**
     * The prior photo that this photo replaced, if any.
     *
     * @return HasOne<Photo, $this>
     */
    public function replacedPhoto(): HasOne
    {
        return $this->hasOne(Photo::class, 'replaced_by_photo_id');
    }

    /**
     * @return HasOne<PhotoApproval, $this>
     */
    public function photoApproval(): HasOne
    {
        return $this->hasOne(PhotoApproval::class);
    }

    public function isPending(): bool
    {
        /** @var PhotoStatus $status */
        $status = $this->status;

        return $status === PhotoStatus::PENDING;
    }

    public function isReplaceable(): bool
    {
        /** @var PhotoStatus $status */
        $status = $this->status;

        return $status === PhotoStatus::FLAGGED || $status === PhotoStatus::REPLACEMENT_REQUESTED;
    }

    public function isApproved(): bool
    {
        return (bool) $this->is_approved;
    }
}
