<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Templates\DTOs\UpdateCategoryPresetDTO;
use App\Domains\Templates\Models\CategoryPreset;
use Illuminate\Support\Facades\DB;

final class UpdateCategoryPresetAction
{
    public function execute(CategoryPreset $preset, UpdateCategoryPresetDTO $dto): CategoryPreset
    {
        return DB::transaction(function () use ($preset, $dto): CategoryPreset {
            $preset->update([
                'name' => $dto->name,
                'description' => $dto->description,
                'default_picture_count' => $dto->defaultPictureCount,
                'is_required' => $dto->isRequired,
                'is_active' => $dto->isActive,
            ]);

            return $preset->refresh();
        });
    }
}
