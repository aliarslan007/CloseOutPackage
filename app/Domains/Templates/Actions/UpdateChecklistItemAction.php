<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Templates\Enums\TemplateDriftChangeType;
use App\Domains\Templates\Events\TemplateChecklistItemUpdated;
use App\Domains\Templates\Models\ChecklistItem;

final class UpdateChecklistItemAction
{
    public function execute(ChecklistItem $item, string $label, bool $hasExplainField, bool $allowsNa = false): ChecklistItem
    {
        $item->update([
            'label' => $label,
            'has_explain_field' => $hasExplainField,
            'allows_na' => $allowsNa,
        ]);

        if ($item->wasChanged('has_explain_field')) {
            TemplateChecklistItemUpdated::dispatch($item, TemplateDriftChangeType::EXPLAIN_CHANGED);
        }

        return $item;
    }
}
