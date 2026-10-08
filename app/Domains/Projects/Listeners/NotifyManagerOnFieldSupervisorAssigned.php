<?php

declare(strict_types=1);

namespace App\Domains\Projects\Listeners;

use App\Domains\Projects\Events\FieldSupervisorAssigned;
use App\Domains\Projects\Notifications\SupervisorAddedToProjectNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyManagerOnFieldSupervisorAssigned implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(FieldSupervisorAssigned $event): void
    {
        $manager = $event->project->manager;

        if ($manager === null || ($event->actor !== null && $event->actor->id === $manager->id)) {
            return;
        }

        $manager->notify(new SupervisorAddedToProjectNotification($event->project, $event->supervisor));
    }
}
