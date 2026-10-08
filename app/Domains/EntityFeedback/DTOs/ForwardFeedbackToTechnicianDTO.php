<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\DTOs;

final class ForwardFeedbackToTechnicianDTO
{
    public function __construct(
        public int $assignedBy,
        public ?int $assignedToUserId = null,
        public ?string $instructions = null,
    ) {}
}
