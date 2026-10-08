<?php

declare(strict_types=1);

namespace App\Domains\Email\Enums;

enum EmailStatus: string
{
    /** Recorded as an intent, waiting for a manager to release it. */
    case READY = 'ready';

    /** Released for a future send. */
    case SCHEDULED = 'scheduled';

    /** Handed to the queue. */
    case PENDING = 'pending';

    case SENT = 'sent';

    case FAILED = 'failed';

    /** A scheduled/pending send the manager called off before it went out. */
    case CANCELLED = 'cancelled';

    /** Intentionally not sent (customer email kill switch), kept for audit. */
    case SUPPRESSED = 'suppressed';

    /**
     * Whether a queued job is still allowed to put this row on the wire.
     * A cancelled, suppressed or already-sent row must no-op.
     */
    public function isDeliverable(): bool
    {
        return match ($this) {
            self::READY, self::SCHEDULED, self::PENDING => true,
            self::SENT, self::FAILED, self::CANCELLED, self::SUPPRESSED => false,
        };
    }

    /** Still awaiting a manager decision — the actionable part of the outbox. */
    public function isOpen(): bool
    {
        return match ($this) {
            self::READY, self::SCHEDULED => true,
            self::PENDING, self::SENT, self::FAILED, self::CANCELLED, self::SUPPRESSED => false,
        };
    }

    /**
     * Statuses a manager can bring back to the outbox via Resend (a new row
     * is created; the original is never touched).
     */
    public function canResend(): bool
    {
        return ! $this->isDeliverable();
    }

    public function label(): string
    {
        return match ($this) {
            self::READY => 'Not sent',
            self::SCHEDULED => 'Scheduled',
            self::PENDING => 'Sending',
            self::SENT => 'Sent',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
            self::SUPPRESSED => 'Suppressed',
        };
    }
}
