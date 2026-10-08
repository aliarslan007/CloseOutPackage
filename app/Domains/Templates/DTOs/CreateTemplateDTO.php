<?php

declare(strict_types=1);

namespace App\Domains\Templates\DTOs;

final readonly class CreateTemplateDTO
{
    public function __construct(
        public int $marketId,
        public string $name,
        public ?string $description = null,
        public bool $isActive = true,
    ) {}
}
