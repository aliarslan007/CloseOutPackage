<?php

declare(strict_types=1);

namespace App\Domains\Approval\Actions;

use App\Domains\Approval\DTOs\ReviewPhotoDTO;
use App\Domains\Approval\Enums\ApprovalDecision;
use App\Domains\Photos\Models\Photo;
use App\Domains\Users\Models\User;

final class RequestPhotoReplacementAction
{
    public function execute(Photo $photo, ?string $reason, User $reviewer): Photo
    {
        return app(ReviewPhotoAction::class)->execute(
            new ReviewPhotoDTO(
                photoId: $photo->id,
                decision: ApprovalDecision::REPLACEMENT_REQUESTED,
                reason: $reason,
            ),
            $reviewer,
        );
    }
}
