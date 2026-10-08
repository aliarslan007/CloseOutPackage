<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Notifications;

use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class FeedbackForwardedToTechnicianNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly EntityFeedbackRound $round,
        public readonly User $manager,
        public readonly ?string $instructions = null,
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
        $isTechnician = $notifiable instanceof User && (
            ($this->round->assigned_to_user_id !== null && $notifiable->id === $this->round->assigned_to_user_id)
            || $notifiable->hasRole(Role::FIELD_SUPERVISOR->value)
        );

        $title = $isTechnician
            ? 'Customer feedback assigned to you'
            : 'Review assigned for field rework';

        $body = $isTechnician
            ? "Manager {$this->manager->name} assigned customer review notes for {$entityLabel} #{$this->round->entity_id} to you for field revision."
            : "Manager {$this->manager->name} assigned your review on {$entityLabel} #{$this->round->entity_id} to a field technician for on-site rework.";

        return [
            'type' => 'feedback_forwarded_to_technician',
            'entity_type' => $this->round->entity_type->value,
            'entity_id' => $this->round->entity_id,
            'round_id' => $this->round->id,
            'round_number' => $this->round->round_number,
            'manager_id' => $this->manager->id,
            'manager_name' => $this->manager->name,
            'instructions' => $this->instructions,
            'title' => $title,
            'body' => $body,
            'message' => $body,
            'created_at' => now()->toISOString(),
        ];
    }
}
