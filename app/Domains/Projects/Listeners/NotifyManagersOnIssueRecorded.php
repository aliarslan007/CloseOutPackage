<?php

declare(strict_types=1);

namespace App\Domains\Projects\Listeners;

use App\Domains\Projects\Events\IssueRecorded;
use App\Domains\Projects\Notifications\IssueOpenedNotification;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyManagersOnIssueRecorded implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(IssueRecorded $event): void
    {
        $issue = $event->issue->loadMissing(['project', 'reporter']);
        $reporterId = $issue->reported_by;

        $recipientIds = User::role(Role::ADMIN->value)
            ->get(['id'])
            ->pluck('id')
            ->merge($issue->project->fieldSupervisors()->get(['users.id'])->pluck('id'));

        if ($issue->project->manager_id !== null) {
            $recipientIds->push($issue->project->manager_id);
        }

        $recipientIds
            ->unique()
            ->reject(fn (int $userId): bool => $userId === $reporterId)
            ->each(function (int $userId) use ($issue): void {
                User::find($userId)?->notify(new IssueOpenedNotification($issue));
            });
    }
}
