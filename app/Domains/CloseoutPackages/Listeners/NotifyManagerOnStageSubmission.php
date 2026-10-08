<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Listeners;

use App\Domains\CloseoutPackages\Events\StageSubmitted;
use App\Domains\CloseoutPackages\Jobs\SendStageUploadNotificationJob;

class NotifyManagerOnStageSubmission
{
    public function handle(StageSubmitted $event): void
    {
        SendStageUploadNotificationJob::dispatch($event->package, $event->stage);
    }
}
