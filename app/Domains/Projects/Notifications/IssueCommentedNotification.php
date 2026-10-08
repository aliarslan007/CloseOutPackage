<?php

declare(strict_types=1);

namespace App\Domains\Projects\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class IssueCommentedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $projectId,
        private readonly string $projectName,
        private readonly string $authorName,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New comment on issue',
            'project_id' => $this->projectId,
            'body' => "{$this->authorName} commented on an issue for project \"{$this->projectName}\".",
        ];
    }
}
