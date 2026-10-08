<?php

declare(strict_types=1);

namespace App\Domains\Projects\Listeners;

use App\Domains\Projects\Events\FieldSupervisorRemoved;
use App\Domains\Projects\Notifications\SupervisorRemovedFromProjectNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyManagerOnFieldSupervisorRemoved implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(FieldSupervisorRemoved $event): void
    {
        $manager = $event->project->manager;

        if ($manager === null || ($event->actor !== null && $event->actor->id === $manager->id)) {
            return;
        }

        $manager->notify(new SupervisorRemovedFromProjectNotification($event->project, $event->supervisor));
    }
}
