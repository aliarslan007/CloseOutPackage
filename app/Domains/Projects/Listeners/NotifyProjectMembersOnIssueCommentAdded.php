<?php

declare(strict_types=1);

namespace App\Domains\Projects\Listeners;

use App\Domains\Projects\Events\IssueCommentAdded;
use App\Domains\Projects\Notifications\IssueCommentedNotification;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyProjectMembersOnIssueCommentAdded implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(IssueCommentAdded $event): void
    {
        $comment = $event->comment->loadMissing(['issue.project', 'author']);

        $recipientIds = User::role(Role::ADMIN->value)
            ->get(['id'])
            ->pluck('id')
            ->merge($comment->issue->project->fieldSupervisors()->get(['users.id'])->pluck('id'));

        if ($comment->issue->project->manager_id !== null) {
            $recipientIds->push($comment->issue->project->manager_id);
        }

        $recipientIds
            ->unique()
            ->reject(fn (int $userId): bool => $userId === $comment->user_id)
            ->each(function (int $userId) use ($comment): void {
                User::find($userId)?->notify(new IssueCommentedNotification(
                    $comment->issue->project_id,
                    $comment->issue->project->name,
                    $comment->author->name,
                ));
            });
    }
}
