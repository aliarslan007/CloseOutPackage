<?php

declare(strict_types=1);

namespace App\Domains\Templates\DTOs;

final readonly class CreateTemplatePictureCategoryDTO
{
    public function __construct(
        public int $templateSectionId,
        public string $name,
        public ?string $description = null,
        public int $defaultPictureCount = 1,
        public int $sortOrder = 0,
        public bool $isRequired = false,
    ) {}
}
