<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Notifications;

use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * The customer came back with another question after receiving an answer, so the
 * round has re-entered the manager's queue.
 */
final class FeedbackFollowUpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly EntityFeedbackRound $round,
        public readonly User $customer,
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
        $body = "{$this->customer->name} added a follow-up to {$entityLabel} #{$this->round->entity_id}. Please review.";

        return [
            'type' => 'feedback_follow_up',
            'entity_type' => $this->round->entity_type->value,
            'entity_id' => $this->round->entity_id,
            'round_id' => $this->round->id,
            'round_number' => $this->round->round_number,
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'title' => 'Customer added a follow-up',
            'body' => $body,
            'message' => $body,
            'created_at' => now()->toISOString(),
        ];
    }
}
