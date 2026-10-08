<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Enums;

enum SowStatus: string
{
    case DRAFT = 'draft';
    case IN_REVIEW = 'in_review'; // set by SubmitSowForReviewAction
    case PUBLISHED = 'published'; // set by GenerateSowPdfJob on success
    case ARCHIVED = 'archived'; // Admin only; terminal state

    /** Legal transition map — checked in every status-mutation Action. */
    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::DRAFT => in_array($next, [self::IN_REVIEW, self::PUBLISHED, self::ARCHIVED], true),
            self::IN_REVIEW => in_array($next, [self::DRAFT, self::PUBLISHED, self::ARCHIVED], true),
            self::PUBLISHED => in_array($next, [self::DRAFT, self::IN_REVIEW, self::ARCHIVED], true), // reopen path
            self::ARCHIVED => in_array($next, [self::PUBLISHED, self::IN_REVIEW, self::DRAFT], true), // unarchive path
        };
    }
}
