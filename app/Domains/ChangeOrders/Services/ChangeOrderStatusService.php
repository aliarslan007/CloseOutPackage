<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Services;

use App\Domains\ChangeOrders\Enums\ChangeOrderStatus;
use App\Domains\ChangeOrders\Enums\CustomerDecision;
use App\Domains\ChangeOrders\Models\ChangeOrder;

/**
 * Single source of truth for deriving a change order's status from
 * its customers' per-item decisions (plan §7).
 */
final class ChangeOrderStatusService
{
    public function recompute(ChangeOrder $changeOrder): ChangeOrderStatus
    {
        $included = $changeOrder->items()->where('is_included', true)->get(['customer_decision']);

        $status = $this->derive($included->pluck('customer_decision')->map(
            fn (?CustomerDecision $decision): ?string => $decision?->value,
        )->all());

        $changeOrder->forceFill(['status' => $status])->save();

        return $status;
    }

    /**
     * @param  list<string|null>  $decisions
     */
    private function derive(array $decisions): ChangeOrderStatus
    {
        $pendingCount = count(array_filter($decisions, fn (?string $d): bool => $d === null));
        $approvedCount = count($decisions) - $pendingCount;

        if ($pendingCount === count($decisions)) {
            return ChangeOrderStatus::SENT_TO_CUSTOMER;
        }

        if ($pendingCount > 0) {
            // Partial responses in — customer still owes the rest.
            return ChangeOrderStatus::CUSTOMER_ACTION_REQUIRED;
        }

        $allApproved = ! in_array(CustomerDecision::REJECTED->value, $decisions, true);
        $allRejected = ! in_array(CustomerDecision::APPROVED->value, $decisions, true);

        return match (true) {
            $allApproved => ChangeOrderStatus::APPROVED,
            $allRejected => ChangeOrderStatus::REJECTED,
            default => ChangeOrderStatus::PARTIALLY_APPROVED,
        };
    }
}
