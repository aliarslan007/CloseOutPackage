<?php

declare(strict_types=1);

namespace App\Domains\Approval\DTOs;

final readonly class BulkReviewPhotosDTO
{
    /**
     * @param  list<array{photo_id: int, decision: string, reason?: ?string}>  $items
     */
    public function __construct(public array $items) {}
}
