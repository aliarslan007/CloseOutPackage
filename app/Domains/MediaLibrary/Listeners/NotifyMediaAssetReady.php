<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Listeners;

use App\Domains\MediaLibrary\Events\MediaAssetUploaded;
use App\Domains\MediaLibrary\Notifications\MediaAssetReadyNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyMediaAssetReady implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(MediaAssetUploaded $event): void
    {
        $owner = $event->asset->owner;

        if ($owner === null) {
            return;
        }

        $owner->notify(new MediaAssetReadyNotification($event->asset));
    }
}
