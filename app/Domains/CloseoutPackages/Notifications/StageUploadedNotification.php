<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Notifications;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\Email\Channels\SendCopEmailChannel;
use App\Domains\Email\Contracts\SendsCopEmail;
use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\Services\EmailComposerService;
use App\Domains\Templates\Enums\StageType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StageUploadedNotification extends Notification implements SendsCopEmail, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly CloseoutPackage $package,
        private readonly PackageStage $stage,
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
        return app(EmailComposerService::class)->composeStageUpload($this->stage);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        /** @var StageType $stageType */
        $stageType = $this->stage->stage;
        $siteName = $this->package->project->name ?? '';
        $stageLabel = match ($stageType) {
            StageType::PRE_CONSTRUCTION => 'Pre Construction',
            StageType::CONSTRUCTION => 'Construction',
            StageType::POST_COMPLETION => 'Post Completion',
        };

        return [
            'title' => 'Stage submitted',
            'package_id' => $this->package->id,
            'project_id' => $this->package->project_id,
            'stage' => $stageType->value,
            'site_name' => $siteName,
            'body' => "{$stageLabel} for {$siteName} has been submitted for review.",
        ];
    }
}
