<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\DTOs;

/**
 * @param  list<ChangeOrderItemDTO>  $items
 * @param  list<int>  $recipientIds
 */
final readonly class ChangeOrderDTO
{
    /**
     * @param  list<ChangeOrderItemDTO>  $items
     * @param  list<int>  $recipientIds
     * @param  list<int>  $mediaAssetIds
     * @param  list<int>|null  $photoIds
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public array $items,
        public ?string $changeOrderDate = null,
        public array $recipientIds = [],
        public array $mediaAssetIds = [],
        public ?array $photoIds = null,
    ) {}

    /** Total of billed items only — what the customer will actually be billed. */
    public function includedTotal(): float
    {
        return array_sum(array_map(
            fn (ChangeOrderItemDTO $item): float => ($item->isIncluded && ! $item->costAbsorbedBySolucomp) ? $item->amount : 0.0,
            $this->items,
        ));
    }

    /** Total of complimentary items absorbed by Solucomp. */
    public function absorbedTotal(): float
    {
        return array_sum(array_map(
            fn (ChangeOrderItemDTO $item): float => ($item->isIncluded && $item->costAbsorbedBySolucomp) ? $item->amount : 0.0,
            $this->items,
        ));
    }
}
