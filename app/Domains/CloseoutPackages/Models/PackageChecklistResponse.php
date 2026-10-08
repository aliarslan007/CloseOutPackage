<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Models;

use App\Domains\Templates\Models\ChecklistItem;
use Database\Factories\PackageChecklistResponseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PackageChecklistResponse extends Model implements HasMedia
{
    /** @use HasFactory<PackageChecklistResponseFactory> */
    use HasFactory, InteractsWithMedia;

    public const SITE_NOTES_COLLECTION = 'checklist_response_notes';

    /** Maximum images a technician may attach to a single checklist answer. */
    public const SITE_NOTES_IMAGE_LIMIT = 6;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'closeout_package_id',
        'checklist_item_id',
        'answer',
        'is_na',
        'explain',
        'quantity',
    ];

    protected static function newFactory(): PackageChecklistResponseFactory
    {
        return PackageChecklistResponseFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answer' => 'boolean',
            'is_na' => 'boolean',
            'quantity' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::SITE_NOTES_COLLECTION);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Generated inline: note images are uploaded at low volume during drafting,
        // and both the checklist grid and the PDF need them deterministically.
        $this->addMediaConversion('thumb')->width(320)->nonQueued();

        $this->addMediaConversion('pdf')->width(1400)->nonQueued();
    }

    /**
     * @return BelongsTo<CloseoutPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(CloseoutPackage::class, 'closeout_package_id');
    }

    /**
     * @return BelongsTo<ChecklistItem, $this>
     */
    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class)->withTrashed();
    }
}
