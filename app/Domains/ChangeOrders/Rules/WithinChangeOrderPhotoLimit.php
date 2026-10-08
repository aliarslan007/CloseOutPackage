<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Rules;

use App\Domains\ChangeOrders\Models\ChangeOrder;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Cumulative photo-cap guard. media_asset_ids is per-request, but a change
 * order can be updated repeatedly, so the cap must count photos already
 * attached — minus the ones being detached in this same payload (passed in as
 * $keptPhotoIds from photo_ids). Re-picked library assets are not double-counted.
 */
final class WithinChangeOrderPhotoLimit implements ValidationRule
{
    /**
     * @param  list<int>|null  $keptPhotoIds  null = keep every existing photo
     */
    public function __construct(
        private readonly ChangeOrder $changeOrder,
        private readonly ?array $keptPhotoIds = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $query = $this->changeOrder->photos();

        if ($this->keptPhotoIds !== null) {
            $query->whereIn('id', array_map(intval(...), $this->keptPhotoIds));
        }

        $retained = $query->get(['id', 'media_asset_id']);
        $alreadyAttached = $retained->pluck('media_asset_id')->filter()->map(intval(...))->all();
        $requested = array_unique(array_map(intval(...), (array) $value));

        if ($retained->count() + count(array_diff($requested, $alreadyAttached)) > ChangeOrder::MAX_PHOTOS) {
            $fail(sprintf(
                'A change order can include at most %d photos. Remove some before adding more.',
                ChangeOrder::MAX_PHOTOS,
            ));
        }
    }
}
