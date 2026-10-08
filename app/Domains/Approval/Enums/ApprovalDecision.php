<?php

declare(strict_types=1);

namespace App\Domains\Approval\Enums;

enum ApprovalDecision: string
{
    case APPROVED = 'approved';
    case FLAGGED = 'flagged';
    case REJECTED = 'rejected';
    case REPLACEMENT_REQUESTED = 'replacement_requested';
}
