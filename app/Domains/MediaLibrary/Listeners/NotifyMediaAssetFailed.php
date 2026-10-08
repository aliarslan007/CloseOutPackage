<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Listeners;

use App\Domains\MediaLibrary\Events\MediaAssetProcessingFailed;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Notifications\MediaAssetFailedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyMediaAssetFailed implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(MediaAssetProcessingFailed $event): void
    {
        $asset = MediaAsset::query()->find($event->assetId);

        if ($asset === null) {
            return;
        }

        $owner = $asset->owner;

        if ($owner === null) {
            return;
        }

        $owner->notify(new MediaAssetFailedNotification($asset, $event->message));
    }
}
