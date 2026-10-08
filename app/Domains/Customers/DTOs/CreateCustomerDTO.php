<?php

declare(strict_types=1);

namespace App\Domains\Customers\DTOs;

final readonly class CreateCustomerDTO
{
    public function __construct(
        public string $name,
        public string $code,
        public ?string $description = null,
        public bool $isActive = true,
    ) {}
}
