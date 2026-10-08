<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Actions;

use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\EntityFeedback\Notifications\TechnicianRevisionsSubmittedNotification;
use App\Domains\EntityFeedback\Services\FeedbackEntityResolver;
use App\Domains\Users\Models\User;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\DB;

final class SubmitTechnicianFeedbackToManagerAction
{
    public function __construct(
        private readonly FeedbackEntityResolver $entities,
    ) {}

    public function execute(EntityFeedbackRound $round, User $actor): EntityFeedbackRound
    {
        if (! $round->isSubmitted()) {
            throw new InvalidStateTransitionException(
                'Only submitted rounds with technician assignment can be handed back to manager.'
            );
        }

        if ($round->technician_submitted_at !== null) {
            throw new InvalidStateTransitionException(
                'Revisions have already been submitted to the manager.'
            );
        }

        return DB::transaction(function () use ($round, $actor): EntityFeedbackRound {
            $round->update([
                'technician_submitted_at' => now(),
                'technician_submitted_by' => $actor->id,
            ]);

            $manager = $this->entities->managerFor($round);
            $manager?->notify(new TechnicianRevisionsSubmittedNotification($round, $actor));

            return $round;
        });
    }
}
