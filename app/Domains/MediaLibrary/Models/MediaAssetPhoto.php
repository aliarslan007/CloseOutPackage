<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Models;

use App\Domains\Photos\Models\Photo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot linking a media-library asset to the COP Photo(s) created from it.
 * Unique on (media_asset_id, photo_id) — an asset maps to each Photo once.
 */
final class MediaAssetPhoto extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'media_asset_id',
        'photo_id',
    ];

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    /**
     * @return BelongsTo<Photo, $this>
     */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }
}
