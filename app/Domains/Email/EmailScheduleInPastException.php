<?php

declare(strict_types=1);

namespace App\Domains\Email;

use App\Shared\Exceptions\CopException;
use Carbon\CarbonInterface;

/**
 * A manager tried to schedule an email in the past. Rendered as 422 via the
 * CopException hook; the message states the earliest allowed moment so the
 * stale-tab case self-explains.
 */
final class EmailScheduleInPastException extends CopException
{
    public function __construct(public readonly CarbonInterface $requested)
    {
        parent::__construct(
            'The scheduled time is in the past. The earliest you can schedule from here is '.now()->format('M j, Y g:ia').'.',
        );
    }
}
