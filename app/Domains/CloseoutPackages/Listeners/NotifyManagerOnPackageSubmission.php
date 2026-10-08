<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Listeners;

use App\Domains\CloseoutPackages\Events\PackageSubmitted;
use App\Domains\CloseoutPackages\Jobs\SendPackageSubmittedNotificationJob;

class NotifyManagerOnPackageSubmission
{
    public function handle(PackageSubmitted $event): void
    {
        SendPackageSubmittedNotificationJob::dispatch($event->package, $event->submitter);
    }
}
