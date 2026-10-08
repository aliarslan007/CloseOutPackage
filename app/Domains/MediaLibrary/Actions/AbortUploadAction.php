<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Actions;

use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Services\ChunkStorageService;
use App\Domains\MediaLibrary\Services\MediaQuotaService;
use App\Shared\Exceptions\UploadStateConflictException;
use Illuminate\Support\Facades\Gate;

final class AbortUploadAction
{
    public function execute(MediaAsset $asset): void
    {
        // Owner-or-admin check against the currently authenticated user.
        Gate::authorize('delete', $asset);

        if ($asset->status !== MediaAssetStatus::PROCESSING) {
            throw new UploadStateConflictException(
                $asset->status === MediaAssetStatus::READY
                    ? 'Upload already completed.'
                    : 'Upload was cancelled or failed.',
            );
        }

        // Orphaned chunks are deleted, the row is removed outright (nothing
        // was ever committed) and the used-bytes cache is invalidated.
        app(ChunkStorageService::class)->cleanup($asset->id);
        app(MediaQuotaService::class)->invalidateUsedCache($asset->user_id);

        $asset->forceDelete();
    }
}
