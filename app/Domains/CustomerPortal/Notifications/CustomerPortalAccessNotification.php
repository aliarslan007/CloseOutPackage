<?php

declare(strict_types=1);

namespace App\Domains\CustomerPortal\Notifications;

use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\Email\Channels\SendCopEmailChannel;
use App\Domains\Email\Contracts\SendsCopEmail;
use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\Services\EmailComposerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CustomerPortalAccessNotification extends Notification implements SendsCopEmail, ShouldQueue
{
    use Queueable;

    public function __construct(private readonly CustomerProjectAccess $access) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', SendCopEmailChannel::class];
    }

    public function toSendCopEmail(object $notifiable): ?SendEmailDTO
    {
        return app(EmailComposerService::class)->composeCustomerAccessGranted($this->access);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $projectName = $this->access->project->name ?? '';

        return [
            'title' => 'Portal access granted',
            'project_id' => $this->access->project_id,
            'project_name' => $projectName,
            'body' => "You now have access to \"{$projectName}\" in the customer portal.",
        ];
    }
}
