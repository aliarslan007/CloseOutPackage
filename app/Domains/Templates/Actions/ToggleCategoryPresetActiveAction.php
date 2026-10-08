<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Templates\Models\CategoryPreset;
use Illuminate\Support\Facades\DB;

final class ToggleCategoryPresetActiveAction
{
    public function execute(CategoryPreset $preset): CategoryPreset
    {
        return DB::transaction(function () use ($preset): CategoryPreset {
            $preset->update(['is_active' => ! $preset->is_active]);

            return $preset->refresh();
        });
    }
}
