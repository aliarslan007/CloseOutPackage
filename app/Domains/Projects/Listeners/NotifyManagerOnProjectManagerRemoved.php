<?php

declare(strict_types=1);

namespace App\Domains\Projects\Listeners;

use App\Domains\Projects\Events\ProjectManagerRemoved;
use App\Domains\Projects\Notifications\ProjectUnassignedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyManagerOnProjectManagerRemoved implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(ProjectManagerRemoved $event): void
    {
        $event->manager->notify(new ProjectUnassignedNotification($event->project));
    }
}
