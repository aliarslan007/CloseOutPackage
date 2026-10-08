<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\DTOs;

final readonly class SaveDraftDTO
{
    /**
     * @param  list<array{category_id: int, is_selected: bool, picture_count: int, user_heading?: ?string, user_notes?: ?string}>  $categorySelections
     * @param  list<array{checklist_item_id: int, answer: ?bool, explain?: ?string, is_na?: bool}>|null  $checklistResponses
     */
    public function __construct(
        public array $categorySelections,
        public ?array $checklistResponses = null,
    ) {}
}
