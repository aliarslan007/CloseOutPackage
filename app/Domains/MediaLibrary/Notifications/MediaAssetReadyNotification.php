<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Notifications;

use App\Domains\MediaLibrary\Models\MediaAsset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MediaAssetReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly MediaAsset $asset) {}

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

        return [
            'asset_id' => $this->asset->id,
            'project_id' => $this->asset->project_id,
            'filename' => $name,
            'title' => 'Photo ready',
            'body' => "Your photo \"{$name}\" has finished processing and is ready in the media library.",
        ];
    }
}
