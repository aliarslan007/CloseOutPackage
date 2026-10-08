<?php

declare(strict_types=1);

namespace App\Domains\Approval\Listeners;

use App\Domains\Approval\Events\ReplacementRequested;
use App\Domains\Approval\Jobs\SendReplacementRequestNotificationJob;

class NotifyFieldTechOnReplacementRequest
{
    public function handle(ReplacementRequested $event): void
    {
        SendReplacementRequestNotificationJob::dispatch($event->photo);
    }
}
