<?php

declare(strict_types=1);

namespace App\Domains\Approval\Notifications;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Email\Channels\SendCopEmailChannel;
use App\Domains\Email\Contracts\SendsCopEmail;
use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\Services\EmailComposerService;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PackageFinalApprovalNotification extends Notification implements SendsCopEmail, ShouldQueue
{
    use Queueable;

    public function __construct(private readonly CloseoutPackage $package) {}

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

        return app(EmailComposerService::class)->composeFinalApproval($this->package, $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'package_id' => $this->package->id,
            'project_id' => $this->package->project_id,
            'body' => "Package #{$this->package->id} has received final approval.",
        ];
    }
}
