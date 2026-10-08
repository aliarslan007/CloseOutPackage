<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Enums;

/**
 * Field technician location-reporting state. None of these mean anything about a
 * login session: `ACTIVE` only says the phone sent a GPS fix recently. Keep the
 * labels framed around location ("not reporting", not "logged out") or managers
 * read them as sign-in state.
 */
enum TrackingStatus: string
{
    case ACTIVE = 'active';
    case IDLE = 'idle';
    case OFFLINE = 'offline';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }

    /**
     * Mirrors STATUS_LABELS in resources/js/features/tracking/types/tracking.types.ts.
     */
    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Live',
            self::IDLE => 'Paused',
            self::OFFLINE => 'Not Reporting',
        };
    }
}
