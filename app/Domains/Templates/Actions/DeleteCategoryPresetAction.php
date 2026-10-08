<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Templates\Models\CategoryPreset;
use Illuminate\Support\Facades\DB;

final class DeleteCategoryPresetAction
{
    public function execute(CategoryPreset $preset): void
    {
        DB::transaction(function () use ($preset): void {
            $preset->delete();
        });
    }
}
