<?php

declare(strict_types=1);

namespace App\Domains\Templates\DTOs;

final readonly class ReorderCategoriesDTO
{
    /**
     * @param  list<array{id: int, sort_order: int}>  $items
     */
    public function __construct(public array $items) {}
}
