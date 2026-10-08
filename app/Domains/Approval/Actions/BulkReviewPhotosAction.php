<?php

declare(strict_types=1);

namespace App\Domains\Approval\Actions;

use App\Domains\Approval\DTOs\BulkReviewPhotosDTO;
use App\Domains\Approval\DTOs\ReviewPhotoDTO;
use App\Domains\Approval\Enums\ApprovalDecision;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;

final class BulkReviewPhotosAction
{
    public function execute(BulkReviewPhotosDTO $dto, User $reviewer): void
    {
        DB::transaction(function () use ($dto, $reviewer): void {
            foreach ($dto->items as $item) {
                $decision = ApprovalDecision::tryFrom($item['decision']);

                if ($decision === null) {
                    continue;
                }

                app(ReviewPhotoAction::class)->execute(
                    new ReviewPhotoDTO(
                        photoId: $item['photo_id'],
                        decision: $decision,
                        reason: $item['reason'] ?? null,
                    ),
                    $reviewer,
                );
            }
        });
    }
}
