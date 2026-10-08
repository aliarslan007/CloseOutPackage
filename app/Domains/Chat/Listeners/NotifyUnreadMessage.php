<?php

declare(strict_types=1);

namespace App\Domains\Chat\Listeners;

use App\Domains\Chat\Events\ChatMessageSent;
use App\Domains\Chat\Notifications\ChatMessageReceivedNotification;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyUnreadMessage implements ShouldQueue
{
    public function handle(ChatMessageSent $event): void
    {
        $project = Project::query()->find($event->message->project_id);

        if ($project === null) {
            return;
        }

        $recipient = $this->resolveRecipient($project, $event->message->sender_id);

        if ($recipient === null) {
            return;
        }

        $recipient->notify(new ChatMessageReceivedNotification($event->message));
    }

    private function resolveRecipient(Project $project, int $senderId): ?User
    {
        if ($project->manager_id !== null && $project->manager_id !== $senderId) {
            return User::query()->find($project->manager_id);
        }

        return User::query()
            ->whereIn('id', $project->fieldSupervisors()->pluck('users.id'))
            ->whereKeyNot($senderId)
            ->first();
    }
}
