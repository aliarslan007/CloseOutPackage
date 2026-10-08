<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Notifications;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Email\Channels\SendCopEmailChannel;
use App\Domains\Email\Contracts\SendsCopEmail;
use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\Services\EmailComposerService;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PackageSubmittedNotification extends Notification implements SendsCopEmail, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly CloseoutPackage $package,
        private readonly User $submitter,
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
        return app(EmailComposerService::class)->composePackageSubmitted($this->package, $this->submitter);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->package->loadMissing('project');

        $siteName = $this->package->project->name ?? '';

        return [
            'title' => 'Package submitted for review',
            'package_id' => $this->package->id,
            'project_id' => $this->package->project_id,
            'site_name' => $siteName,
            'body' => "Close-out package #{$this->package->id} for {$siteName} was submitted for review by {$this->submitter->name}.",
        ];
    }
}
