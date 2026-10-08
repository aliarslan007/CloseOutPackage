<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Actions;

use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\EntityFeedback\Notifications\FeedbackIssueWithdrawnNotification;
use App\Domains\EntityFeedback\Services\FeedbackEntityResolver;
use App\Domains\Users\Models\User;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\DB;

/**
 * Takes one issue off the customer's list without touching the rest of the
 * review. An issue is the conversation the client is reading, so the caller
 * hands over the message ids that make it up rather than a target to re-match:
 * the thread grouping already has one definition on the client and one in the
 * presenter, and a third here would be the first to drift.
 */
final class WithdrawFeedbackIssueAction
{
    public function __construct(
        private readonly FeedbackEntityResolver $entities,
    ) {}

    /**
     * @param  list<int>  $noteIds
     */
    public function execute(EntityFeedbackRound $round, User $actor, array $noteIds): EntityFeedbackRound
    {
        return DB::transaction(function () use ($round, $actor, $noteIds): EntityFeedbackRound {
            if (! $this->onlyNotesOwnedBy($round, $actor->id, $noteIds)) {
                throw new InvalidStateTransitionException('Only the person who raised an issue can withdraw it.');
            }

            // Scoped to this round's notes, so a stale or tampered id list can never
            // reach into another review. Already-withdrawn notes keep the moment and
            // the person that first withdrew them: withdrawing twice is not an edit.
            $withdrawn = $round->notes()
                ->whereIn('id', $noteIds)
                ->where('created_by', $actor->id)
                ->whereNull('withdrawn_at')
                ->update([
                    'withdrawn_at' => now(),
                    'withdrawn_by' => $actor->id,
                ]);

            // Nothing newly dropped - no state change, so no reason to ping anyone.
            if ($withdrawn > 0) {
                $this->notifyStaff($round, $actor, $withdrawn);
            }

            // Withdrawal is the customer saying they do not want the work.
            // Once every turn in the review is withdrawn, the review itself is
            // done - leaving it submitted only forced one more "approve & close".
            $hasLiveIssue = $round->notes()->whereNull('withdrawn_at')->exists();
            $hasAnyIssue = $round->notes()->exists();

            if ($hasAnyIssue && ! $hasLiveIssue && ! $round->isAccepted()) {
                $round->update([
                    'status' => FeedbackRoundStatus::ACCEPTED,
                    'accepted_at' => now(),
                    'accepted_by' => $actor->id,
                    'resolved_at' => $round->resolved_at ?? now(),
                    'resolved_by' => $round->resolved_by ?? $actor->id,
                ]);
            }

            return $round;
        });
    }

    /**
     * @param  list<int>  $noteIds
     */
    private function onlyNotesOwnedBy(EntityFeedbackRound $round, int $actorId, array $noteIds): bool
    {
        return ! $round->notes()
            ->whereIn('id', $noteIds)
            ->where('created_by', '!=', $actorId)
            ->whereNull('withdrawn_at')
            ->exists();
    }

    /**
     * The manager owns the review; the technician, once assigned, is the one
     * physically doing the fix. Both stop work on a withdrawn issue, so both
     * hear about it. Deduped in case the two are the same person.
     */
    private function notifyStaff(EntityFeedbackRound $round, User $actor, int $withdrawn): void
    {
        $notification = new FeedbackIssueWithdrawnNotification($round, $actor, $withdrawn);

        $recipients = collect([
            $this->entities->managerFor($round),
            $round->assignedToUser,
        ])
            ->filter()
            ->unique('id');

        foreach ($recipients as $recipient) {
            $recipient->notify($notification);
        }
    }
}
