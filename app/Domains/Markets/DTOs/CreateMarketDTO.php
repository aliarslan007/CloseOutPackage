<?php

declare(strict_types=1);

namespace App\Domains\Markets\DTOs;

final readonly class CreateMarketDTO
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public bool $isActive = true,
    ) {}
}
