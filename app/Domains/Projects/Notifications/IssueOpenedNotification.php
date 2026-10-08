<?php

declare(strict_types=1);

namespace App\Domains\Projects\Notifications;

use App\Domains\Email\Channels\SendCopEmailChannel;
use App\Domains\Email\Contracts\SendsCopEmail;
use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\Services\EmailComposerService;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class IssueOpenedNotification extends Notification implements SendsCopEmail, ShouldQueue
{
    use Queueable;

    public function __construct(private readonly IssueHistory $issue) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', SendCopEmailChannel::class];
    }

    public function toSendCopEmail(object $notifiable): ?SendEmailDTO
    {
        if (! $notifiable instanceof User) {
            return null;
        }

        return app(EmailComposerService::class)->composeIssueOpened($this->issue, $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->issue->loadMissing('project');

        return [
            'title' => 'Issue opened',
            'project_id' => $this->issue->project_id,
            'body' => "An issue has been opened on project \"{$this->issue->project->name}\".",
        ];
    }
}
