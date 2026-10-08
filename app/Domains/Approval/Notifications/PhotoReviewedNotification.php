<?php

declare(strict_types=1);

namespace App\Domains\Approval\Notifications;

use App\Domains\Approval\Enums\ApprovalDecision;
use App\Domains\Photos\Models\Photo;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PhotoReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Photo $photo,
        private readonly ApprovalDecision $decision,
        private readonly User $reviewer,
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
        $categoryName = $this->photo->categorySelection->templatePictureCategory->name ?? 'a category';
        $reason = $this->photo->flag_reason;

        return [
            'photo_id' => $this->photo->id,
            'package_id' => $this->photo->closeout_package_id,
            'category_name' => $categoryName,
            'title' => match ($this->decision) {
                ApprovalDecision::APPROVED => 'Photo approved',
                ApprovalDecision::FLAGGED => 'Photo flagged',
                ApprovalDecision::REJECTED => 'Photo rejected',
                ApprovalDecision::REPLACEMENT_REQUESTED => 'Replacement requested',
            },
            'body' => match ($this->decision) {
                ApprovalDecision::APPROVED => "Your photo in '{$categoryName}' was approved by {$this->reviewer->name}.",
                ApprovalDecision::FLAGGED => "Your photo in '{$categoryName}' was flagged as blurry by {$this->reviewer->name}.".($reason !== null ? " Reason: {$reason}" : ''),
                ApprovalDecision::REJECTED => "Your photo in '{$categoryName}' was rejected by {$this->reviewer->name}.".($reason !== null ? " Reason: {$reason}" : ''),
                ApprovalDecision::REPLACEMENT_REQUESTED => "A replacement was requested for your photo in '{$categoryName}'.",
            },
        ];
    }
}
