<?php

declare(strict_types=1);

namespace App\Domains\CustomerPortal\Notifications;

use App\Domains\Email\Channels\SendCopEmailChannel;
use App\Domains\Email\Contracts\SendsCopEmail;
use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\Services\EmailComposerService;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ManagerCustomerAccessNotification extends Notification implements SendsCopEmail, ShouldQueue
{
    use Queueable;

    /**
     * @param  'granted'|'revoked'  $action
     */
    public function __construct(
        public readonly string $action,
        public readonly User $manager,
        public readonly User $customer,
        public readonly Project $project,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', SendCopEmailChannel::class];
    }

    public function toSendCopEmail(object $notifiable): ?SendEmailDTO
    {
        if (! $notifiable instanceof User) {
            return null;
        }

        return app(EmailComposerService::class)->composeManagerCustomerAccessChange(
            $this->project,
            $this->manager,
            $this->customer,
            $this->action,
            $notifiable,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $actionVerb = $this->action === 'granted' ? 'granted' : 'revoked';

        return [
            'title' => "Customer access {$actionVerb}",
            'project_id' => $this->project->id,
            'project_name' => $this->project->name,
            'manager_id' => $this->manager->id,
            'manager_name' => $this->manager->name,
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'action' => $this->action,
            'body' => "Manager {$this->manager->name} {$actionVerb} customer access for {$this->customer->name} on project \"{$this->project->name}\".",
        ];
    }
}
