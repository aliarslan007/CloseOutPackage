<?php

declare(strict_types=1);

namespace App\Domains\Projects\Listeners;

use App\Domains\Projects\Events\ProjectStatusChanged;

class LogProjectStatusChange
{
    public function handle(ProjectStatusChanged $event): void
    {
        activity('project')
            ->performedOn($event->project)
            ->event('status.changed')
            ->withProperties(['from' => $event->from->value, 'to' => $event->to->value])
            ->log('Project status changed');
    }
}
