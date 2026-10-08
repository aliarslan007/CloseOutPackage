<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Enums;

enum FeedbackEntityType: string
{
    case CHANGE_ORDER = 'change_order';
    case ISSUE = 'issue';
    case CLOSEOUT_PACKAGE = 'closeout_package';

    public function label(): string
    {
        return match ($this) {
            self::CHANGE_ORDER => 'Change Order',
            self::ISSUE => 'Issue History',
            self::CLOSEOUT_PACKAGE => 'Close-Out Package',
        };
    }
}
