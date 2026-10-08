<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Templates\DTOs\CreateTemplatePictureCategoryDTO;
use App\Domains\Templates\Models\TemplatePictureCategory;

final class CreateTemplatePictureCategoryAction
{
    public function execute(CreateTemplatePictureCategoryDTO $dto): TemplatePictureCategory
    {
        return TemplatePictureCategory::create([
            'template_section_id' => $dto->templateSectionId,
            'name' => $dto->name,
            'description' => $dto->description,
            'default_picture_count' => $dto->defaultPictureCount,
            'sort_order' => $dto->sortOrder,
            'is_required' => $dto->isRequired,
        ]);
    }
}
