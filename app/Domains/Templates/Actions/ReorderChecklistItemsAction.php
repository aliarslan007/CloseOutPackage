<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Templates\DTOs\ReorderCategoriesDTO;
use App\Domains\Templates\Models\Template;
use Illuminate\Support\Facades\DB;

final class ReorderChecklistItemsAction
{
    public function execute(Template $template, ReorderCategoriesDTO $dto): void
    {
        DB::transaction(function () use ($template, $dto): void {
            foreach ($dto->items as $item) {
                $template->checklistItems()
                    ->whereKey($item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });
    }
}
