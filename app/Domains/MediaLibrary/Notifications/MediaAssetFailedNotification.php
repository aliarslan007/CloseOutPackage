<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Notifications;

use App\Domains\MediaLibrary\Models\MediaAsset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MediaAssetFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly MediaAsset $asset,
        private readonly string $reason,
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
        $name = $this->asset->original_filename;
        $shortReason = mb_substr($this->reason, 0, 240);

        return [
            'asset_id' => $this->asset->id,
            'project_id' => $this->asset->project_id,
            'filename' => $name,
            'title' => 'Photo upload failed',
            'body' => "Your photo \"{$name}\" could not be processed: {$shortReason}",
        ];
    }
}
