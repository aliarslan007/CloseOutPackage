<?php

declare(strict_types=1);

namespace App\Domains\Email\Enums;

/**
 * How an email gets delivered. This is the single switch that decides both
 * whether the customer kill switch applies (anything but INTERNAL) and
 * whether a manager has to release the email manually (MANUAL).
 *
 * Not persisted — it is derived from EmailType.
 */
enum DeliveryMode
{
    /** Send immediately, no manager involvement. */
    case AUTO;

    /** Only sent after a manager releases it from the Customer Emails outbox. */
    case MANUAL;

    /** Never emailed at all. */
    case NONE;

    /** Staff-only notification, outside the customer consent model. */
    case INTERNAL;
}
