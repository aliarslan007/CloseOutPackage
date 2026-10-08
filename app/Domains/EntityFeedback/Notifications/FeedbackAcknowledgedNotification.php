<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Notifications;

use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Tells the customer their feedback was received and work has started. Without
 * it, a submitted round is silent and the customer cannot tell it apart from one
 * that was never opened.
 */
final class FeedbackAcknowledgedNotification extends Notification implements ShouldQueue
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
        $body = "{$this->manager->name} has read your feedback on {$entityLabel} #{$this->round->entity_id} and has started working on it.";

        return [
            'type' => 'feedback_acknowledged',
            'entity_type' => $this->round->entity_type->value,
            'entity_id' => $this->round->entity_id,
            'round_id' => $this->round->id,
            'round_number' => $this->round->round_number,
            'manager_id' => $this->manager->id,
            'manager_name' => $this->manager->name,
            'title' => 'Your feedback was received',
            'body' => $body,
            'message' => $body,
            'created_at' => now()->toISOString(),
        ];
    }
}
