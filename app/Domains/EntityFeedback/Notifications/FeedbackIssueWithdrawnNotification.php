<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Notifications;

use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Tells the manager (and the technician mid-fix) that the customer has taken an
 * issue off the list. Without it staff keep chasing a change nobody wants any
 * more - the whole point of a withdrawal is that the work stops.
 */
final class FeedbackIssueWithdrawnNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly EntityFeedbackRound $round,
        public readonly User $customer,
        public readonly int $withdrawnCount,
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
        $noun = $this->withdrawnCount === 1 ? 'issue' : 'issues';
        $text = "{$this->customer->name} withdrew {$this->withdrawnCount} {$noun} on Round "
            ."{$this->round->round_number} review of {$entityLabel} #{$this->round->entity_id}."
            .' No fix is needed on them.';

        return [
            'type' => 'feedback_issue_withdrawn',
            'entity_type' => $this->round->entity_type->value,
            'entity_id' => $this->round->entity_id,
            'round_id' => $this->round->id,
            'round_number' => $this->round->round_number,
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'withdrawn_count' => $this->withdrawnCount,
            // The notification centre renders data['title'] and data['body'].
            'title' => 'Customer withdrew an issue',
            'body' => $text,
            'message' => $text,
            'created_at' => now()->toISOString(),
        ];
    }
}
