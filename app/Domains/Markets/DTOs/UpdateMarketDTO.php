<?php

declare(strict_types=1);

namespace App\Domains\Markets\DTOs;

final readonly class UpdateMarketDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?bool $isActive = null,
    ) {}
}
