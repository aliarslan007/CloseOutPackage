<?php

declare(strict_types=1);

namespace App\Domains\Approval\Notifications;

use App\Domains\Email\Channels\SendCopEmailChannel;
use App\Domains\Email\Contracts\SendsCopEmail;
use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\Services\EmailComposerService;
use App\Domains\Photos\Models\Photo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PhotoReplacementRequestedNotification extends Notification implements SendsCopEmail, ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Photo $photo) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', SendCopEmailChannel::class];
    }

    public function toSendCopEmail(object $notifiable): ?SendEmailDTO
    {
        return app(EmailComposerService::class)->composeReplacementRequest($this->photo);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $photo = $this->photo->load(['package', 'categorySelection.templatePictureCategory']);
        $category = $photo->categorySelection?->templatePictureCategory;

        return [
            'photo_id' => $photo->id,
            'package_id' => $photo->closeout_package_id,
            'category_name' => $category !== null ? $category->name : 'Unknown category',
            'body' => 'A replacement photo has been requested for '.($category !== null ? $category->name : 'a category').'.',
        ];
    }
}
