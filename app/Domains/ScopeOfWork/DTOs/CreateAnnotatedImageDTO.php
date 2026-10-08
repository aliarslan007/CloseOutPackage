<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\DTOs;

final readonly class CreateAnnotatedImageDTO
{
    public function __construct(
        public int $sowReportId,
        public int $mediaAssetId,
    ) {}
}
