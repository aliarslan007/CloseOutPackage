<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Notifications;

use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class TechnicianRevisionsSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly EntityFeedbackRound $round,
        public readonly User $technician,
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
            'type' => 'technician_revisions_submitted',
            'entity_type' => $this->round->entity_type->value,
            'entity_id' => $this->round->entity_id,
            'round_id' => $this->round->id,
            'round_number' => $this->round->round_number,
            'technician_id' => $this->technician->id,
            'technician_name' => $this->technician->name,
            'title' => 'Field revisions submitted',
            'body' => "Technician {$this->technician->name} completed rework on {$entityLabel} #{$this->round->entity_id} and submitted it for your review.",
            'message' => "Technician {$this->technician->name} completed rework on {$entityLabel} #{$this->round->entity_id} and submitted it for your review.",
            'created_at' => now()->toISOString(),
        ];
    }
}
