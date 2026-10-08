<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\DTOs;

final readonly class AttachSowToProjectDTO
{
    public function __construct(
        public int $sowReportId,
        public int $projectId,
    ) {}
}
