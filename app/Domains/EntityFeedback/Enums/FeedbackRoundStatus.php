<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Enums;

enum FeedbackRoundStatus: string
{
    case OPEN = 'open';
    case SUBMITTED = 'submitted';
    case RESOLVED = 'resolved';
    case ACCEPTED = 'accepted';

    public function isEditableByCustomer(): bool
    {
        // OPEN is their draft; RESOLVED lets them reply to the answer instead of
        // being forced to accept or start over.
        return $this === self::OPEN || $this === self::RESOLVED;
    }

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open Draft',
            self::SUBMITTED => 'Submitted to Manager',
            self::RESOLVED => 'Manager Resolved',
            self::ACCEPTED => 'Accepted by Customer',
        };
    }
}
