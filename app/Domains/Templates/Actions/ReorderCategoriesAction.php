<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Templates\DTOs\ReorderCategoriesDTO;
use App\Domains\Templates\Models\TemplateSection;
use Illuminate\Support\Facades\DB;

final class ReorderCategoriesAction
{
    public function execute(TemplateSection $section, ReorderCategoriesDTO $dto): void
    {
        DB::transaction(function () use ($section, $dto): void {
            foreach ($dto->items as $item) {
                $section->categories()
                    ->whereKey($item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });
    }
}
