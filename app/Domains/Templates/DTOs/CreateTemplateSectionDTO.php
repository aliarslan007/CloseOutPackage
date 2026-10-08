<?php

declare(strict_types=1);

namespace App\Domains\Templates\DTOs;

use App\Domains\Templates\Enums\StageType;

final readonly class CreateTemplateSectionDTO
{
    public function __construct(
        public int $templateId,
        public StageType $stage,
        public string $heading,
        public ?string $adminNotes = null,
        public int $sortOrder = 0,
    ) {}
}
