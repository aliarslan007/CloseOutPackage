<?php

declare(strict_types=1);

namespace App\Domains\Email;

use App\Domains\Email\Enums\EmailStatus;
use App\Domains\Email\Models\EmailLog;
use App\Shared\Exceptions\CopException;

/**
 * A manager tried an outbox action the row's current status does not allow
 * (e.g. Send now on an already-sent email, or cancelling a delivered one).
 * Rendered as 422 by ExceptionRenderer via the CopException hook, so the
 * manager sees a plain-language message instead of a stack trace.
 */
final class InvalidOutboxTransitionException extends CopException
{
    public function __construct(
        public readonly EmailLog $log,
        public readonly string $action,
    ) {
        $status = $log->status instanceof EmailStatus ? $log->status->label() : (string) $log->status;

        parent::__construct(
            "Cannot {$action} this email: it is {$status}. Refresh the Customer Emails page to see its current state.",
        );
    }
}
