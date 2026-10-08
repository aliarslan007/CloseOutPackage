<?php

declare(strict_types=1);

namespace App\Domains\Projects\DTOs;

use App\Domains\Projects\Enums\IssueType;

final readonly class RecordIssueDTO
{
    public function __construct(
        public IssueType $type,
        public string $description,
        public ?string $verizonGuidance = null,
    ) {}
}
