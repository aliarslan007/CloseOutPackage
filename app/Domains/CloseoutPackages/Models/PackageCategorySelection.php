<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Models;

use App\Domains\Photos\Models\Photo;
use App\Domains\Templates\Models\TemplatePictureCategory;
use Database\Factories\PackageCategorySelectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $uploaded_photos
 * @property-read int $active_photo_count
 */
class PackageCategorySelection extends Model
{
    /** @use HasFactory<PackageCategorySelectionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'package_stage_id',
        'template_picture_category_id',
        'is_selected',
        'picture_count',
        'user_heading',
        'user_notes',
    ];

    protected static function newFactory(): PackageCategorySelectionFactory
    {
        return PackageCategorySelectionFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_selected' => 'boolean',
            'picture_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PackageStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(PackageStage::class, 'package_stage_id');
    }

    /**
     * @return BelongsTo<PackageStage, $this>
     */
    public function packageStage(): BelongsTo
    {
        return $this->stage();
    }

    /**
     * @return BelongsTo<TemplatePictureCategory, $this>
     */
    public function templatePictureCategory(): BelongsTo
    {
        return $this->belongsTo(TemplatePictureCategory::class)->withTrashed();
    }

    /**
     * @return HasMany<Photo, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class, 'package_category_selection_id');
    }
}
