<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Notifications;

use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class CustomerFeedbackSubmittedNotification extends Notification implements ShouldQueue
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

        return [
            'type' => 'customer_feedback_submitted',
            'entity_type' => $this->round->entity_type->value,
            'entity_id' => $this->round->entity_id,
            'round_id' => $this->round->id,
            'round_number' => $this->round->round_number,
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'note_count' => $this->round->notes()->count(),
            // The notification centre renders data['title'] and data['body'];
            // without these it showed a bare "Notification" with no text.
            'title' => 'New customer feedback',
            'body' => "{$this->customer->name} submitted Round {$this->round->round_number} review notes on {$entityLabel} #{$this->round->entity_id}.",
            'message' => "{$this->customer->name} submitted Round {$this->round->round_number} review notes on {$entityLabel} #{$this->round->entity_id}.",
            'created_at' => now()->toISOString(),
        ];
    }
}
