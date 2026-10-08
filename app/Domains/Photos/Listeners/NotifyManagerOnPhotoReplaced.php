<?php

declare(strict_types=1);

namespace App\Domains\Photos\Listeners;

use App\Domains\Photos\Events\PhotoReplaced;
use App\Domains\Photos\Jobs\SendPhotoReplacedNotificationJob;

class NotifyManagerOnPhotoReplaced
{
    public function handle(PhotoReplaced $event): void
    {
        SendPhotoReplacedNotificationJob::dispatch($event->oldPhoto, $event->newPhoto);
    }
}
