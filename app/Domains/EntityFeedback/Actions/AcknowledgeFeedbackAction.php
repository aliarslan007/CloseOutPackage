<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Actions;

use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\EntityFeedback\Notifications\FeedbackAcknowledgedNotification;
use App\Domains\EntityFeedback\Services\FeedbackEntityResolver;
use App\Domains\Users\Models\User;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\DB;

/**
 * The manager confirms they have read a customer's submission and are starting
 * on it.
 *
 * Without this the customer's feedback disappears into an inbox: they have no
 * way of telling "received" from "ignored". Acknowledging is the promise that
 * work has started, and it notifies the customer so they see it.
 */
final class AcknowledgeFeedbackAction
{
    public function __construct(
        private readonly FeedbackEntityResolver $entities,
    ) {}

    public function execute(EntityFeedbackRound $round, User $actor): EntityFeedbackRound
    {
        if (! $round->isSubmitted()) {
            throw new InvalidStateTransitionException(
                'Only feedback that has been submitted can be acknowledged.'
            );
        }

        if ($round->isAcknowledged()) {
            throw new InvalidStateTransitionException('This feedback has already been acknowledged.');
        }

        return DB::transaction(function () use ($round, $actor): EntityFeedbackRound {
            $round->update([
                'manager_acknowledged_at' => now(),
                'manager_acknowledged_by' => $actor->id,
            ]);

            $customer = $round->submittedBy ?? $round->openedBy;
            $customer?->notify(new FeedbackAcknowledgedNotification($round, $actor));

            return $round;
        });
    }

    /**
     * The project manager who owns this round, used by the policy to keep an
     * acknowledgement from being made on somebody else's project.
     */
    public function managerFor(EntityFeedbackRound $round): ?User
    {
        return $this->entities->managerFor($round);
    }
}
