<?php

declare(strict_types=1);

namespace App\Domains\Projects\Notifications;

use App\Domains\Email\Channels\SendCopEmailChannel;
use App\Domains\Email\Contracts\SendsCopEmail;
use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\Services\EmailComposerService;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ProjectCreatedNotification extends Notification implements SendsCopEmail, ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Project $project) {}

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

        return app(EmailComposerService::class)->composeProjectCreated($this->project, $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New project created',
            'project_id' => $this->project->id,
            'project_name' => $this->project->name,
            'body' => "Project \"{$this->project->name}\" has been created.",
        ];
    }
}
