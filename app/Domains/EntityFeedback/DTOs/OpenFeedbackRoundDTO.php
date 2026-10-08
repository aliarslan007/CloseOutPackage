<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\DTOs;

use App\Domains\EntityFeedback\Enums\FeedbackEntityType;

final class OpenFeedbackRoundDTO
{
    public function __construct(
        public FeedbackEntityType $entityType,
        public int $entityId,
        public int $openedBy,
        public ?string $managerInstructions = null,
    ) {}
}
