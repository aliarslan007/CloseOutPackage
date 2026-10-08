<?php

declare(strict_types=1);

namespace App\Domains\Projects\Listeners;

use App\Domains\Projects\Events\FieldSupervisorRemoved;
use App\Domains\Projects\Notifications\ProjectUnassignedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifySupervisorOnFieldSupervisorRemoved implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(FieldSupervisorRemoved $event): void
    {
        $event->supervisor->notify(new ProjectUnassignedNotification($event->project));
    }
}
