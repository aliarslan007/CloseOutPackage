<?php

declare(strict_types=1);

namespace App\Domains\Chat\Actions;

use App\Domains\Chat\Models\ChatMessage;
use App\Domains\Chat\Notifications\ChatMessageReceivedNotification;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;

final class MarkMessagesReadAction
{
    public function execute(Project $project, User $user): int
    {
        $updated = ChatMessage::query()
            ->where('project_id', $project->id)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $user->notifications()
            ->where('type', ChatMessageReceivedNotification::class)
            ->whereNull('read_at')
            ->where('data->project_id', $project->id)
            ->update(['read_at' => now()]);

        return $updated;
    }
}
