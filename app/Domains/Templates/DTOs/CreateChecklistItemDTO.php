<?php

declare(strict_types=1);

namespace App\Domains\Templates\DTOs;

final readonly class CreateChecklistItemDTO
{
    public function __construct(
        public int $templateId,
        public string $label,
        public bool $hasExplainField = false,
        public bool $allowsNa = false,
        public int $sortOrder = 0,
    ) {}
}
