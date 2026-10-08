<?php

declare(strict_types=1);

namespace App\Domains\Customers\DTOs;

final readonly class UpdateCustomerDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $code = null,
        public ?string $description = null,
        public ?bool $isActive = null,
    ) {}
}
