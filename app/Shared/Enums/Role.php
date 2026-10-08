<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum Role: string
{
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case FIELD_SUPERVISOR = 'field_supervisor';
    case CUSTOMER = 'customer';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $role): string => $role->value, self::cases());
    }

    /**
     * Roles managed in the Users module. Customers (incl. customer engineers)
     * are portal accounts — created and managed under App Settings →
     * Customer Account Settings, never here.
     *
     * @return list<string>
     */
    public static function internalValues(): array
    {
        return array_values(array_filter(
            self::values(),
            fn (string $role): bool => $role !== self::CUSTOMER->value
        ));
    }
}
