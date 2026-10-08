<?php

declare(strict_types=1);

namespace App\Domains\Templates\DTOs;

final readonly class SaveFavoriteTemplateDTO
{
    /**
     * @param  array<int, array<string, mixed>>  $snapshot
     */
    public function __construct(
        public int $userId,
        public int $templateId,
        public string $name,
        public array $snapshot,
    ) {}
}
