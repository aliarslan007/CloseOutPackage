<?php

declare(strict_types=1);

namespace App\Domains\Templates\DTOs;

final readonly class UpdateTemplateDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?bool $isActive = null,
        public ?int $marketId = null,
    ) {}
}
