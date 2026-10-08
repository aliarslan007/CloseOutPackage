<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\DTOs;

final readonly class SowTextBoxLayoutDTO
{
    public function __construct(
        public float $fontSize,
        public float $height,
        public float $y,
        public int $linesCount,
        public bool $isExpanded,
    ) {}
}
