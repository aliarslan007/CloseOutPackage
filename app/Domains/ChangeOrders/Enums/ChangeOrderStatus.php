<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Enums;

enum ChangeOrderStatus: string
{
    case DRAFT = 'draft';
    case SENT_TO_CUSTOMER = 'sent_to_customer';
    case CUSTOMER_ACTION_REQUIRED = 'customer_action_required';
    case PARTIALLY_APPROVED = 'partially_approved';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }

    /** Human label used in badges and emails. */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SENT_TO_CUSTOMER => 'Sent to Customer',
            self::CUSTOMER_ACTION_REQUIRED => 'Customer Action Required',
            self::PARTIALLY_APPROVED => 'Partially Approved',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
        };
    }

    /** Badge color class per status, shared with the frontend status pill. */
    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SENT_TO_CUSTOMER => 'blue',
            self::CUSTOMER_ACTION_REQUIRED => 'amber',
            self::PARTIALLY_APPROVED => 'violet',
            self::APPROVED => 'emerald',
            self::REJECTED => 'red',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::DRAFT;
    }

    public function isDecided(): bool
    {
        return in_array($this, [self::APPROVED, self::REJECTED, self::PARTIALLY_APPROVED], true);
    }
}
