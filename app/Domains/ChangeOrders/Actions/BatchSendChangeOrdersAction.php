<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Actions;

use App\Domains\ChangeOrders\DTOs\BatchSendChangeOrdersResult;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

final class BatchSendChangeOrdersAction
{
    public function __construct(
        private readonly SendChangeOrderAction $sendAction,
    ) {}

    /**
     * @param  list<int>  $changeOrderIds
     */
    public function execute(array $changeOrderIds, User $user): BatchSendChangeOrdersResult
    {
        $sentCount = 0;
        $skippedCount = 0;
        $failures = [];

        $changeOrders = ChangeOrder::query()
            ->whereIn('id', $changeOrderIds)
            ->with(['items', 'recipients', 'project'])
            ->get();

        foreach ($changeOrders as $changeOrder) {
            $identifier = $changeOrder->title ?: "CO-#{$changeOrder->id}";

            if (! $user->can('send', $changeOrder)) {
                $skippedCount++;
                $failures[$changeOrder->id] = "You are not authorized to send '{$identifier}' or it is already finalized.";

                continue;
            }

            if (! $changeOrder->recipients()->exists()) {
                $skippedCount++;
                $failures[$changeOrder->id] = "Change order '{$identifier}' has no assigned Verizon customer contacts.";

                continue;
            }

            if (! $changeOrder->items()->where('is_included', true)->exists()) {
                $skippedCount++;
                $failures[$changeOrder->id] = "Change order '{$identifier}' has no included items.";

                continue;
            }

            try {
                $this->sendAction->execute($changeOrder);
                $sentCount++;
            } catch (Throwable $e) {
                Log::error('Failed to send change order in batch', [
                    'change_order_id' => $changeOrder->id,
                    'error' => $e->getMessage(),
                ]);
                $skippedCount++;
                $failures[$changeOrder->id] = "Failed to send '{$identifier}': {$e->getMessage()}";
            }
        }

        return new BatchSendChangeOrdersResult(
            sentCount: $sentCount,
            skippedCount: $skippedCount,
            failures: $failures,
        );
    }
}
