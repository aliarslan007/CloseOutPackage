<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Enums;

enum CustomerDecision: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $decision): string => $decision->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
        };
    }
}
