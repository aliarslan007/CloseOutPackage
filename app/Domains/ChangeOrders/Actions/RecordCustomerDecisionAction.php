<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Actions;

use App\Domains\ChangeOrders\Enums\ChangeOrderStatus;
use App\Domains\ChangeOrders\Enums\CustomerDecision;
use App\Domains\ChangeOrders\Events\ChangeOrderDecided;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderItem;
use App\Domains\ChangeOrders\Models\ChangeOrderRecipient;
use App\Domains\ChangeOrders\Notifications\ChangeOrderDecidedNotification;
use App\Domains\ChangeOrders\Services\ChangeOrderStatusService;
use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use App\Domains\Users\Models\User;
use App\Domains\Users\Queries\UserQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class RecordCustomerDecisionAction
{
    public function __construct(
        private readonly ChangeOrderStatusService $statusService,
        private readonly UserQuery $userQuery,
    ) {}

    /**
     * Record a per-item (or bulk) decision and recompute the parent status.
     *
     * @param  array<int, array{item_id: int, decision: CustomerDecision, note?: ?string}>  $decisions
     */
    public function execute(ChangeOrder $changeOrder, array $decisions, User $decidedBy): ChangeOrder
    {
        return DB::transaction(function () use ($changeOrder, $decisions, $decidedBy): ChangeOrder {
            foreach ($decisions as $entry) {
                /** @var ChangeOrderItem|null $item */
                $item = $changeOrder->items()->whereKey($entry['item_id'])->lockForUpdate()->first();

                if ($item === null || ! $item->is_included) {
                    continue; // edge case: excluded/foreign items silently ignored
                }

                $item->update([
                    'customer_decision' => $entry['decision'],
                    'customer_decision_note' => $entry['note'] ?? null,
                    'decided_at' => now(),
                ]);
            }

            $this->statusService->recompute($changeOrder->refresh());
            $this->closeReviewIfDecided($changeOrder, $decidedBy);

            // One decision per recipient: recording it also revokes the
            // emailed token so the link cannot be replayed.
            $changeOrder->recipients()
                ->where('user_id', $decidedBy->id)
                ->get()
                ->each(fn (ChangeOrderRecipient $recipient): null => $recipient->recordResponse());

            $changeOrder->loadMissing(['project.manager', 'creator']);

            $usersToNotify = collect();

            // 1. All Admins
            $usersToNotify = $usersToNotify->merge($this->userQuery->admins());

            // 2. Assigned Project Manager only (never global managers)
            if ($changeOrder->project?->manager !== null) {
                $usersToNotify->push($changeOrder->project->manager);
            }

            // 3. Creator
            if ($changeOrder->creator !== null) {
                $usersToNotify->push($changeOrder->creator);
            }

            $uniqueRecipients = $usersToNotify->unique('id')->values();

            if ($uniqueRecipients->isNotEmpty()) {
                Notification::send($uniqueRecipients, new ChangeOrderDecidedNotification($changeOrder, $decidedBy));
            }

            ChangeOrderDecided::dispatch($changeOrder, $decidedBy);

            return $changeOrder->refresh();
        });
    }

    /**
     * Ruling on the items ends the review loop.
     *
     * Approving a change order while its review round is still open would leave
     * the customer free to keep annotating a decision that is already final, so
     * the round is closed alongside the decision rather than left dangling.
     */
    private function closeReviewIfDecided(ChangeOrder $changeOrder, User $decidedBy): void
    {
        $decidedStatuses = [
            ChangeOrderStatus::APPROVED->value,
            ChangeOrderStatus::PARTIALLY_APPROVED->value,
            ChangeOrderStatus::REJECTED->value,
        ];

        if (! in_array($changeOrder->status->value, $decidedStatuses, true)) {
            return;
        }

        $open = $changeOrder->activeFeedbackRound()->first();

        $open?->update([
            'status' => FeedbackRoundStatus::ACCEPTED,
            'accepted_at' => now(),
            'accepted_by' => $decidedBy->id,
        ]);
    }
}
