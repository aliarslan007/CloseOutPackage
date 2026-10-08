<?php

declare(strict_types=1);

namespace App\Domains\CustomerPortal\DTOs;

use Carbon\CarbonImmutable;

final readonly class GrantCustomerAccessDTO
{
    /**
     * @param  list<int>  $projectIds
     */
    public function __construct(
        public int $userId,
        public array $projectIds,
        public ?int $grantedBy = null,
        public ?CarbonImmutable $expiresAt = null,
    ) {}
}
