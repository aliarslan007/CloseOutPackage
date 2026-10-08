<?php

declare(strict_types=1);

namespace App\Domains\Projects\Listeners;

use App\Domains\Projects\Events\FieldSupervisorAssigned;
use App\Domains\Projects\Notifications\ProjectAssignedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifySupervisorOnFieldSupervisorAssigned implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(FieldSupervisorAssigned $event): void
    {
        $event->supervisor->notify(new ProjectAssignedNotification($event->project));
    }
}
