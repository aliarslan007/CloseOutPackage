<?php

declare(strict_types=1);

namespace App\Domains\Projects\Notifications;

use App\Domains\Projects\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ProjectUnassignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Project $project) {}

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
            'title' => 'Project unassigned',
            'project_id' => $this->project->id,
            'body' => "Project \"{$this->project->name}\" is no longer assigned to you.",
        ];
    }
}
