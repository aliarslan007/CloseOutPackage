<?php

declare(strict_types=1);

namespace App\Domains\Email;

use App\Shared\Exceptions\CopException;
use Carbon\CarbonInterface;

/**
 * A manager tried to schedule an email beyond the allowed horizon
 * (DeliverCustomerEmailAction::MAX_SCHEDULE_DAYS). Rendered as 422 via the
 * CopException hook.
 */
final class EmailScheduleTooFarAheadException extends CopException
{
    public function __construct(
        public readonly CarbonInterface $requested,
        public readonly int $maxDays,
    ) {
        parent::__construct("Emails can be scheduled at most {$maxDays} days ahead.");
    }
}
