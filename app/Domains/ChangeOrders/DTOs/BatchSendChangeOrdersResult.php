<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\DTOs;

final readonly class BatchSendChangeOrdersResult
{
    /**
     * @param  array<int, string>  $failures  changeOrderId => failure reason
     */
    public function __construct(
        public int $sentCount,
        public int $skippedCount,
        public array $failures = [],
    ) {}

    public function hasFailures(): bool
    {
        return $this->failures !== [];
    }
}
