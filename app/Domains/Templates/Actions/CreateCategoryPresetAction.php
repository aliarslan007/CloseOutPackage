<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Templates\DTOs\CreateCategoryPresetDTO;
use App\Domains\Templates\Models\CategoryPreset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class CreateCategoryPresetAction
{
    public function execute(CreateCategoryPresetDTO $dto): CategoryPreset
    {
        return DB::transaction(function () use ($dto): CategoryPreset {
            return CategoryPreset::create([
                'name' => $dto->name,
                'description' => $dto->description,
                'default_picture_count' => $dto->defaultPictureCount,
                'is_required' => $dto->isRequired,
                'is_active' => $dto->isActive,
                'created_by' => Auth::id(),
            ]);
        });
    }
}
