<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\DTOs;

final readonly class UpdatePackageChecklistDTO
{
    /**
     * @param  list<array{checklist_item_id: int, answer: ?bool, explain?: ?string, is_na?: bool, quantity?: ?int}>  $responses
     */
    public function __construct(public array $responses) {}
}
