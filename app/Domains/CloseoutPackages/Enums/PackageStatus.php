<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Enums;

enum PackageStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case UNDER_REVIEW = 'under_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    /**
     * Customer feedback & revision loop (extends APPROVED non-destructively).
     */
    case CUSTOMER_REVIEW = 'customer_review';
    case FEEDBACK_RECEIVED = 'feedback_received';
    case REVISION_IN_PROGRESS = 'revision_in_progress';
}
