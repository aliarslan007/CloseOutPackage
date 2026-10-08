<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Actions;

use App\Domains\MediaLibrary\Models\MediaAsset;
use Illuminate\Support\Facades\Gate;

final class UpdateMediaAssetAction
{
    /**
     * Re-tag the asset onto another project (or clear the tag). Uploaded
     * bytes, MIME, filename and capture data are immutable — a READY asset
     * may have been copied into Photos, so only organizational metadata moves.
     */
    public function execute(MediaAsset $asset, ?int $projectId): MediaAsset
    {
        Gate::authorize('update', $asset);

        $asset->update(['project_id' => $projectId]);

        return $asset->refresh();
    }
}
