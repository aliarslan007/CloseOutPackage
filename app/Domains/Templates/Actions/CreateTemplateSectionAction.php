<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Templates\DTOs\CreateTemplateSectionDTO;
use App\Domains\Templates\Models\TemplateSection;

final class CreateTemplateSectionAction
{
    public function execute(CreateTemplateSectionDTO $dto): TemplateSection
    {
        return TemplateSection::create([
            'template_id' => $dto->templateId,
            'stage' => $dto->stage,
            'heading' => $dto->heading,
            'admin_notes' => $dto->adminNotes,
            'sort_order' => $dto->sortOrder,
        ]);
    }
}
