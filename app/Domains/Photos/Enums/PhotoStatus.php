<?php

declare(strict_types=1);

namespace App\Domains\Photos\Enums;

enum PhotoStatus: string
{
    case PENDING = 'pending';
    case FLAGGED = 'flagged';
    case REJECTED = 'rejected';
    case REPLACEMENT_REQUESTED = 'replacement_requested';
    case APPROVED = 'approved';
}
