<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A photo attached to a change order. Files are copied out of the Media
 * Library onto the private disk, so the change order stays self-contained:
 * it renders for staff, customer-portal recipients and no-auth email-token
 * viewers without going through MediaAssetPolicy (which has no customer path).
 */
final class ChangeOrderPhoto extends Model
{
    protected $appends = ['url'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'change_order_id',
        'media_asset_id',
        'uploaded_by',
        'original_name',
        'path',
        'mime_type',
        'size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function getUrlAttribute(): string
    {
        return route('change-orders.photos.stream', ['photo' => $this->getKey()]);
    }

    /**
     * @return BelongsTo<ChangeOrder, $this>
     */
    public function changeOrder(): BelongsTo
    {
        return $this->belongsTo(ChangeOrder::class);
    }
}
