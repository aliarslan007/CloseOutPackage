<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Actions;

use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Services\MediaQuotaService;
use App\Shared\Exceptions\UploadStateConflictException;
use Illuminate\Support\Facades\Gate;

/**
 * Removes a library asset: soft-delete the row (audit trail), hard-delete the
 * underlying Spatie Media file + conversions and invalidate the used-bytes
 * cache. Linked Photos are NOT deleted — each holds an independent copy.
 */
final class DeleteMediaAssetAction
{
    public function execute(MediaAsset $asset): void
    {
        Gate::authorize('delete', $asset);

        if ($asset->status === MediaAssetStatus::PROCESSING) {
            throw new UploadStateConflictException(
                'Upload still in progress. Cancel it before deleting this file.',
            );
        }

        // Soft-delete (audit trail); pivot rows survive for traceability.
        $asset->delete();

        $this->releaseStoredFiles($asset);
    }

    /**
     * Unlink the stored upload, its preserved clean copy and any generated
     * conversions, then free the uploader's quota. Split from execute() so the
     * project cascade and the admin purge can release bytes for assets they must
     * not gate on per-asset ownership.
     */
    public function releaseStoredFiles(MediaAsset $asset): void
    {
        // Hard-delete the stored file, the preserved original in source_media,
        // and any generated conversions. Missing source_media previously leaked
        // the pre-overwrite original on every single delete.
        $asset->getMedia(MediaAsset::COLLECTION)->each->delete();
        $asset->getMedia(MediaAsset::SOURCE_COLLECTION)->each->delete();

        app(MediaQuotaService::class)->invalidateUsedCache($asset->user_id);
    }
}
