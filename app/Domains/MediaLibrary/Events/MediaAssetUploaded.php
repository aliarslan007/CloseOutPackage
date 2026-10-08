<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Events;

use App\Domains\MediaLibrary\Models\MediaAsset;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once ProcessMediaAssetJob has stored the file and marked the asset
 * ready. Listener: NotifyMediaAssetReady (in-app notification to the uploader).
 */
class MediaAssetUploaded implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly MediaAsset $asset) {}
}
