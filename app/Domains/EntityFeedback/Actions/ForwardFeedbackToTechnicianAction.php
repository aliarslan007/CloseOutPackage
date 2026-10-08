<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Actions;

use App\Domains\EntityFeedback\DTOs\ForwardFeedbackToTechnicianDTO;
use App\Domains\EntityFeedback\Events\FeedbackForwardedToTechnician;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\EntityFeedback\Notifications\FeedbackForwardedToTechnicianNotification;
use App\Domains\EntityFeedback\Services\FeedbackEntityResolver;
use App\Domains\Users\Models\User;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\DB;

final class ForwardFeedbackToTechnicianAction
{
    public function __construct(
        private readonly FeedbackEntityResolver $entities,
    ) {}

    public function execute(
        EntityFeedbackRound $round,
        ForwardFeedbackToTechnicianDTO $dto,
        User $actor,
    ): EntityFeedbackRound {
        if (! $round->isSubmitted()) {
            throw new InvalidStateTransitionException(
                'Only submitted feedback rounds can be forwarded to a technician.'
            );
        }

        return DB::transaction(function () use ($round, $dto, $actor): EntityFeedbackRound {
            $updatedInstructions = $round->manager_instructions;
            if ($dto->instructions !== null && trim($dto->instructions) !== '') {
                $trimmed = trim($dto->instructions);
                $entry = '['.now()->format('M j, Y, g:i A').' - '.$actor->name."]:\n".$trimmed;
                $updatedInstructions = $updatedInstructions
                    ? $updatedInstructions."\n\n".$entry
                    : $entry;
            }

            $round->update([
                'assigned_to_user_id' => $dto->assignedToUserId,
                'assigned_at' => now(),
                'assigned_by' => $dto->assignedBy,
                'technician_submitted_at' => null,
                'technician_submitted_by' => null,
                'manager_instructions' => $updatedInstructions,
                'manager_acknowledged_at' => $round->manager_acknowledged_at ?? now(),
                'manager_acknowledged_by' => $round->manager_acknowledged_by ?? $actor->id,
            ]);

            FeedbackForwardedToTechnician::dispatch($round, $actor, $dto->instructions);

            if ($dto->assignedToUserId !== null) {
                $technician = User::query()->find($dto->assignedToUserId);
                $technician?->notify(
                    new FeedbackForwardedToTechnicianNotification($round, $actor, $dto->instructions)
                );
            } else {
                $project = $this->entities->projectFor($round);
                if ($project !== null) {
                    $project->fieldSupervisors()->get()->each(function (User $tech) use ($round, $actor, $dto): void {
                        $tech->notify(
                            new FeedbackForwardedToTechnicianNotification($round, $actor, $dto->instructions)
                        );
                    });
                }
            }

            $customer = $round->submittedBy ?? $round->openedBy;
            if ($customer !== null && $customer->id !== $actor->id && $customer->id !== $dto->assignedToUserId) {
                $customer->notify(
                    new FeedbackForwardedToTechnicianNotification($round, $actor, $dto->instructions)
                );
            }

            return $round;
        });
    }
}
