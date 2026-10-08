<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Actions;

use App\Domains\EntityFeedback\DTOs\OpenFeedbackRoundDTO;
use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use Illuminate\Support\Facades\DB;

final class OpenFeedbackRoundAction
{
    public function execute(OpenFeedbackRoundDTO $dto): EntityFeedbackRound
    {
        return DB::transaction(function () use ($dto): EntityFeedbackRound {
            /** @var EntityFeedbackRound|null $existingActiveRound */
            $existingActiveRound = EntityFeedbackRound::query()
                ->where('entity_type', $dto->entityType->value)
                ->where('entity_id', $dto->entityId)
                ->whereIn('status', [
                    FeedbackRoundStatus::OPEN,
                    FeedbackRoundStatus::SUBMITTED,
                    FeedbackRoundStatus::RESOLVED,
                ])
                ->orderByDesc('round_number')
                ->lockForUpdate()
                ->first();

            if ($existingActiveRound !== null) {
                return $existingActiveRound;
            }

            $maxRoundNumber = (int) EntityFeedbackRound::query()
                ->where('entity_type', $dto->entityType->value)
                ->where('entity_id', $dto->entityId)
                ->max('round_number');

            /** @var EntityFeedbackRound $round */
            $round = EntityFeedbackRound::query()->create([
                'entity_type' => $dto->entityType,
                'entity_id' => $dto->entityId,
                'round_number' => $maxRoundNumber + 1,
                'status' => FeedbackRoundStatus::OPEN,
                'opened_by' => $dto->openedBy,
                'manager_instructions' => $dto->managerInstructions,
            ]);

            return $round;
        });
    }
}
