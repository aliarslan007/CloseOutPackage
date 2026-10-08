<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Actions;

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderRecipient;

final class MarkChangeOrderViewedAction
{
    /**
     * Marks the change order as read for this customer — the first view wins
     * and the timestamp is never changed afterwards, whether it came through
     * the emailed decision link or the customer portal.
     */
    public function execute(ChangeOrder $changeOrder, int $userId): void
    {
        $changeOrder->recipients()
            ->where('user_id', $userId)
            ->get()
            ->each(fn (ChangeOrderRecipient $recipient): null => $recipient->markViewed());
    }
}
