<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired from ProcessMediaAssetJob::failed() after all retries are exhausted.
 * Listener: NotifyMediaAssetFailed (in-app notification to the uploader).
 */
class MediaAssetProcessingFailed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $assetId,
        public readonly string $message,
    ) {}
}
