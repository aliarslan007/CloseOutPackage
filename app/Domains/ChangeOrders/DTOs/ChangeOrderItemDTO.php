<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\DTOs;

use App\Domains\ChangeOrders\Enums\CustomerDecision;

final readonly class ChangeOrderItemDTO
{
    public function __construct(
        public ?int $id,
        public int $sortOrder,
        public string $description,
        /** @var list<string>|null */
        public ?array $vendorName,
        public ?string $category,
        public float $amount,
        public bool $isIncluded = true,
        public bool $costAbsorbedBySolucomp = false,
        public ?CustomerDecision $customerDecision = null,
        public ?string $customerDecisionNote = null,
    ) {}
}
