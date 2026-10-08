<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Actions;

use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\Users\Models\User;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\DB;

final class AcceptFeedbackRoundAction
{
    public function execute(EntityFeedbackRound $round, User $actor): EntityFeedbackRound
    {
        if ($round->isAccepted()) {
            return $round;
        }

        if (! $round->isSubmitted() && ! $round->isResolved()) {
            throw new InvalidStateTransitionException(
                'Feedback can only be accepted after it has been submitted for review.'
            );
        }

        return DB::transaction(function () use ($round, $actor): EntityFeedbackRound {
            $round->update([
                'status' => FeedbackRoundStatus::ACCEPTED,
                'accepted_at' => now(),
                'accepted_by' => $actor->id,
                'resolved_at' => $round->resolved_at ?? now(),
                'resolved_by' => $round->resolved_by ?? $actor->id,
            ]);

            $round->notes()->whereNull('resolved_at')->update([
                'resolved_at' => now(),
                'resolved_by' => $actor->id,
                'manager_response' => 'Accepted and closed by customer.',
            ]);

            return $round;
        });
    }
}
