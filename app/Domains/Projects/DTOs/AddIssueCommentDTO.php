<?php

declare(strict_types=1);

namespace App\Domains\Projects\DTOs;

final readonly class AddIssueCommentDTO
{
    public function __construct(
        public string $body,
    ) {}
}
