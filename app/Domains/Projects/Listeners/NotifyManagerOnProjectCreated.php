<?php

declare(strict_types=1);

namespace App\Domains\Projects\Listeners;

use App\Domains\Projects\Events\ProjectCreated;
use App\Domains\Projects\Notifications\ProjectCreatedNotification;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyManagerOnProjectCreated implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(ProjectCreated $event): void
    {
        User::role(Role::ADMIN->value)
            ->get()
            ->each(function (User $admin) use ($event): void {
                $admin->notify(new ProjectCreatedNotification($event->project));
            });
    }
}
