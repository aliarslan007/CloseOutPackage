<?php

declare(strict_types=1);

namespace App\Domains\Approval\DTOs;

use App\Domains\Approval\Enums\ApprovalDecision;

final readonly class ReviewPhotoDTO
{
    public function __construct(
        public int $photoId,
        public ApprovalDecision $decision,
        public ?string $reason = null,
    ) {}
}
