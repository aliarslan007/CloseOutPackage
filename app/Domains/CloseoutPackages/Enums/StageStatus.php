<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Enums;

enum StageStatus: string
{
    case NOT_STARTED = 'not_started';
    case IN_PROGRESS = 'in_progress';
    case DRAFT_SAVED = 'draft_saved';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
}
