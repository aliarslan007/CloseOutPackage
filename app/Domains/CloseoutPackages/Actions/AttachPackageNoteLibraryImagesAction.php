<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Actions;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Services\MediaAssetStorageService;
use App\Shared\Exceptions\MediaAssetNotReadyException;
use App\Shared\Exceptions\PhotoCountExceededException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Copies ready Media Library assets into a package's site-notes collection as
 * independent duplicates (the master stays byte-for-byte in the library).
 * The same capacity rule as file uploads applies: PACKAGE_SITE_NOTES_IMAGE_LIMIT.
 */
final class AttachPackageNoteLibraryImagesAction
{
    /**
     * @param  list<int>  $mediaAssetIds
     * @return int number of images attached
     */
    public function execute(array $mediaAssetIds, CloseoutPackage $package): int
    {
        foreach ($mediaAssetIds as $assetId) {
            $asset = MediaAsset::query()->find($assetId);

            if ($asset === null) {
                throw (new ModelNotFoundException)->setModel(MediaAsset::class, [$assetId]);
            }

            Gate::authorize('view', $asset);

            if ($asset->status !== MediaAssetStatus::READY) {
                throw new MediaAssetNotReadyException('This file has not finished processing yet. Please wait a moment and try again.');
            }
        }

        return DB::transaction(function () use ($mediaAssetIds, $package): int {
            // Re-check capacity under lock so a concurrent upload cannot exceed
            // the same limit the file-upload request enforces.
            /** @var CloseoutPackage $package */
            $package = CloseoutPackage::query()->lockForUpdate()->findOrFail($package->getKey());
            $attached = $package->getMedia(CloseoutPackage::PACKAGE_SITE_NOTES_COLLECTION)->count();
            $limit = CloseoutPackage::PACKAGE_SITE_NOTES_IMAGE_LIMIT;

            if ($attached + count($mediaAssetIds) > $limit) {
                $remaining = max(0, $limit - $attached);

                throw new PhotoCountExceededException(
                    $remaining === 0
                        ? "These notes can hold {$limit} images in total, and they are all filled. Remove one before adding another."
                        : "These notes can hold {$limit} images in total — only {$remaining} more can be added. Please remove some and try again.",
                );
            }

            foreach ($mediaAssetIds as $assetId) {
                $asset = MediaAsset::query()->findOrFail($assetId);

                app(MediaAssetStorageService::class)->copyToCollection(
                    $asset,
                    $package,
                    CloseoutPackage::PACKAGE_SITE_NOTES_COLLECTION,
                );
            }

            return count($mediaAssetIds);
        });
    }
}
