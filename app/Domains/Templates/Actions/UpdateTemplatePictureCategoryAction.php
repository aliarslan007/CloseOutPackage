<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Templates\Enums\TemplateDriftChangeType;
use App\Domains\Templates\Events\TemplateCategoryUpdated;
use App\Domains\Templates\Models\TemplatePictureCategory;

final class UpdateTemplatePictureCategoryAction
{
    public function execute(
        TemplatePictureCategory $category,
        string $name,
        ?string $description,
        int $defaultPictureCount,
        bool $isRequired,
    ): TemplatePictureCategory {
        $category->update([
            'name' => $name,
            'description' => $description,
            'default_picture_count' => $defaultPictureCount,
            'is_required' => $isRequired,
        ]);

        $changeType = match (true) {
            $category->wasChanged('default_picture_count') => TemplateDriftChangeType::BUCKET_CHANGED,
            $category->wasChanged('is_required') => TemplateDriftChangeType::REQUIRED_CHANGED,
            default => TemplateDriftChangeType::HEADING_CHANGED,
        };

        TemplateCategoryUpdated::dispatch($category, $changeType);

        return $category;
    }
}
