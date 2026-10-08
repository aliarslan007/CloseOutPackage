<?php

declare(strict_types=1);

namespace App\Domains\Projects\Listeners;

use App\Domains\Projects\Events\ProjectManagerAssigned;
use App\Domains\Projects\Notifications\ProjectAssignedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyManagerOnProjectManagerAssigned implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(ProjectManagerAssigned $event): void
    {
        $event->manager->notify(new ProjectAssignedNotification($event->project));
    }
}
