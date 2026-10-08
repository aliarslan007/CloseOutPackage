<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use App\Domains\Email\Enums\DeliveryMode;
use App\Domains\Email\Enums\EmailType;

/**
 * An email type's delivery configuration makes the send impossible — e.g.
 * DeliveryMode::NONE, or a kill switch with no outbox path available.
 * Distinct from EmailDeliveryException, which is a transport failure.
 */
final class EmailNotConfiguredException extends CopException
{
    public function __construct(
        public readonly EmailType $type,
        public readonly string $detail,
    ) {
        parent::__construct("{$type->label()} emails are not configured for sending ({$detail}).");
    }
}
