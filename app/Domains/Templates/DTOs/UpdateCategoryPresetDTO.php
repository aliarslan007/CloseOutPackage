<?php

declare(strict_types=1);

namespace App\Domains\Templates\DTOs;

final readonly class UpdateCategoryPresetDTO
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public int $defaultPictureCount = 1,
        public bool $isRequired = true,
        public bool $isActive = true,
    ) {}
}
