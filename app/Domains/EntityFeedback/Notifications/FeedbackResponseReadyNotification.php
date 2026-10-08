<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Notifications;

use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class FeedbackResponseReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly EntityFeedbackRound $round,
        public readonly User $manager,
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
        $entityLabel = $this->round->entity_type->label();

        return [
            'type' => 'feedback_response_ready',
            'entity_type' => $this->round->entity_type->value,
            'entity_id' => $this->round->entity_id,
            'round_id' => $this->round->id,
            'round_number' => $this->round->round_number,
            'manager_id' => $this->manager->id,
            'manager_name' => $this->manager->name,
            // The notification centre renders data['title'] and data['body'];
            // without these it showed a bare "Notification" with no text.
            'title' => 'Your feedback has been answered',
            'body' => "Manager {$this->manager->name} resolved your review queries on {$entityLabel} #{$this->round->entity_id}. Please review.",
            'message' => "Manager {$this->manager->name} resolved your review queries on {$entityLabel} #{$this->round->entity_id}. Please review.",
            'created_at' => now()->toISOString(),
        ];
    }
}
