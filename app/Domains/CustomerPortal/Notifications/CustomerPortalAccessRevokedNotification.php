<?php

declare(strict_types=1);

namespace App\Domains\CustomerPortal\Notifications;

use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Access revocation notifies the customer in-app only — no email is sent
 * (EmailType::PORTAL_ACCESS_REVOKED is DeliveryMode::NONE).
 */
class CustomerPortalAccessRevokedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly User $customer,
        private readonly Project $project,
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
        return [
            'title' => 'Portal access revoked',
            'project_id' => $this->project->id,
            'project_name' => $this->project->name,
            'body' => "Access to \"{$this->project->name}\" in the customer portal has been revoked.",
        ];
    }
}
