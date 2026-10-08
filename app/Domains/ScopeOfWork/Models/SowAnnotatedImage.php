<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Models;

use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\ScopeOfWork\Enums\CompositeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class SowAnnotatedImage extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sow_report_id', 'media_asset_id', 'sow_image_group_id', 'annotation_layer',
        'composite_path', 'composite_status', 'sort_order',
        'page_title', 'is_cover', 'include_in_pdf', 'annotations_updated_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'annotation_layer' => 'array',
            'composite_status' => CompositeStatus::class,
            'sow_image_group_id' => 'integer',
            'is_cover' => 'boolean',
            'include_in_pdf' => 'boolean',
            'annotations_updated_at' => 'datetime:Y-m-d H:i:s.u',
        ];
    }

    /**
     * @return BelongsTo<SowReport, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(SowReport::class, 'sow_report_id');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    /**
     * @return BelongsTo<SowImageGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(SowImageGroup::class, 'sow_image_group_id');
    }
}
