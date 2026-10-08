<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\DTOs;

use Carbon\CarbonImmutable;

final readonly class PdfPageDTO
{
    public function __construct(
        public int $pageNumber,
        public int $totalPages,
        public string $revisionLabel,
        public CarbonImmutable $revisionDate,
    ) {}
}
