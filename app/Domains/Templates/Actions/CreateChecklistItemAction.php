<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Templates\DTOs\CreateChecklistItemDTO;
use App\Domains\Templates\Models\ChecklistItem;

final class CreateChecklistItemAction
{
    public function execute(CreateChecklistItemDTO $dto): ChecklistItem
    {
        return ChecklistItem::create([
            'template_id' => $dto->templateId,
            'label' => $dto->label,
            'has_explain_field' => $dto->hasExplainField,
            'allows_na' => $dto->allowsNa,
            'sort_order' => $dto->sortOrder,
        ]);
    }
}
