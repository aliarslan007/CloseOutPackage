<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Actions;

use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use App\Domains\EntityFeedback\Events\FeedbackSubmittedByCustomer;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\EntityFeedback\Notifications\CustomerFeedbackSubmittedNotification;
use App\Domains\EntityFeedback\Services\FeedbackEntityResolver;
use App\Domains\Users\Models\User;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\DB;

final class SubmitFeedbackToManagerAction
{
    public function __construct(
        private readonly FeedbackEntityResolver $entities,
    ) {}

    public function execute(EntityFeedbackRound $round, User $actor): EntityFeedbackRound
    {
        if (! $round->isOpen()) {
            throw new InvalidStateTransitionException(
                'Only open draft feedback rounds can be submitted to the manager.'
            );
        }

        if ($round->notes()->count() === 0) {
            throw new InvalidStateTransitionException(
                'Please add at least one review note before submitting.'
            );
        }

        return DB::transaction(function () use ($round, $actor): EntityFeedbackRound {
            $round->update([
                'status' => FeedbackRoundStatus::SUBMITTED,
                'submitted_at' => now(),
                'submitted_by' => $actor->id,
            ]);

            FeedbackSubmittedByCustomer::dispatch($round, $actor);

            $this->notifyAssignedManager($round, $actor);

            return $round;
        });
    }

    private function notifyAssignedManager(EntityFeedbackRound $round, User $actor): void
    {
        $manager = $this->entities->managerFor($round);
        $manager?->notify(new CustomerFeedbackSubmittedNotification($round, $actor));
    }
}
