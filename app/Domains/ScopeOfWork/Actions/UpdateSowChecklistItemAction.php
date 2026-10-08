<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Actions;

use App\Domains\ScopeOfWork\Models\SowChecklistItem;

final class UpdateSowChecklistItemAction
{
    public function execute(SowChecklistItem $item, string $label, bool $hasExplainField, bool $isRequired): SowChecklistItem
    {
        $item->update([
            'label' => $label,
            'has_explain_field' => $hasExplainField,
            'is_required' => $isRequired,
        ]);

        return $item;
    }
}
