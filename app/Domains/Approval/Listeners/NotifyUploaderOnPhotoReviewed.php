<?php

declare(strict_types=1);

namespace App\Domains\Approval\Listeners;

use App\Domains\Approval\Enums\ApprovalDecision;
use App\Domains\Approval\Events\PhotoReviewed;
use App\Domains\Approval\Notifications\PhotoReviewedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyUploaderOnPhotoReviewed implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(PhotoReviewed $event): void
    {
        // Replacement requests already notify the uploader (bell + email) via
        // the dedicated replacement-requested notification, so skip them here.
        if ($event->decision === ApprovalDecision::REPLACEMENT_REQUESTED) {
            return;
        }

        $uploader = $event->photo->uploader;

        if ($uploader === null) {
            return;
        }

        $uploader->notify(new PhotoReviewedNotification($event->photo, $event->decision, $event->reviewer));
    }
}
