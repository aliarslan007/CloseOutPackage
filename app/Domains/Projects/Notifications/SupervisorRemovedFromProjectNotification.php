<?php

declare(strict_types=1);

namespace App\Domains\Projects\Notifications;

use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SupervisorRemovedFromProjectNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Project $project,
        private readonly User $supervisor,
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
            'title' => 'Supervisor removed from your project',
            'project_id' => $this->project->id,
            'body' => "Supervisor {$this->supervisor->name} is no longer on project \"{$this->project->name}\".",
        ];
    }
}
