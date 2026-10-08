<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Actions;

use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Services\MediaAssetStorageService;
use App\Domains\MediaLibrary\Services\MediaQuotaService;
use App\Domains\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class AnnotateMediaAssetAction
{
    public function __construct(
        private readonly MediaAssetStorageService $storageService,
        private readonly MediaQuotaService $quotaService,
    ) {}

    public function execute(
        MediaAsset $sourceAsset,
        UploadedFile $uploadedFile,
        User $user,
        string $saveMode = 'copy',
        ?array $layer = null,
    ): MediaAsset {
        if ($saveMode === 'overwrite') {
            return $this->overwrite($sourceAsset, $uploadedFile, $layer);
        }

        return $this->saveAsCopy($sourceAsset, $uploadedFile, $user, $layer);
    }

    /**
     * @param  array<string, mixed>|null  $layer
     */
    private function saveAsCopy(
        MediaAsset $sourceAsset,
        UploadedFile $uploadedFile,
        User $user,
        ?array $layer = null,
    ): MediaAsset {
        $fileSize = $uploadedFile->getSize();
        $mimeType = $uploadedFile->getMimeType() ?? 'image/jpeg';
        $realPath = $uploadedFile->getRealPath();

        $this->quotaService->assertQuota($user, $fileSize);

        return DB::transaction(function () use ($sourceAsset, $realPath, $fileSize, $mimeType, $uploadedFile, $user, $layer): MediaAsset {
            $pathInfo = pathinfo($sourceAsset->original_filename);
            $extension = $uploadedFile->guessExtension() ?? ($pathInfo['extension'] ?? 'jpg');
            $newFilename = ($pathInfo['filename'] ?? 'image').'_annotated.'.$extension;

            $rootSourceAssetId = $sourceAsset->source_asset_id ?? $sourceAsset->id;

            /** @var MediaAsset $newAsset */
            $newAsset = MediaAsset::create([
                'user_id' => $user->id,
                'market_id' => $sourceAsset->market_id,
                'project_id' => $sourceAsset->project_id,
                'source_asset_id' => $rootSourceAssetId,
                'annotation_layer' => $layer,
                'original_filename' => $newFilename,
                'mime_type' => $mimeType,
                'file_size_bytes' => $fileSize,
                'total_chunks' => 1,
                'gps_latitude' => $sourceAsset->gps_latitude,
                'gps_longitude' => $sourceAsset->gps_longitude,
                'gps_accuracy' => $sourceAsset->gps_accuracy,
                'captured_at' => $sourceAsset->captured_at ?? now(),
                'status' => MediaAssetStatus::READY,
            ]);

            $this->storageService->storeFromPath(
                $newAsset,
                $realPath,
                $newFilename,
            );

            $this->quotaService->invalidateUsedCache($user->id);

            return $newAsset;
        });
    }

    /**
     * @param  array<string, mixed>|null  $layer
     */
    private function overwrite(
        MediaAsset $sourceAsset,
        UploadedFile $uploadedFile,
        ?array $layer = null,
    ): MediaAsset {
        $fileSize = $uploadedFile->getSize();
        $mimeType = $uploadedFile->getMimeType() ?? 'image/jpeg';
        $realPath = $uploadedFile->getRealPath();

        return DB::transaction(function () use ($sourceAsset, $realPath, $fileSize, $mimeType, $layer): MediaAsset {
            // If this asset doesn't have a parent source asset and hasn't saved its clean original yet, preserve the original file
            if ($sourceAsset->source_asset_id === null && ! $sourceAsset->hasMedia(MediaAsset::SOURCE_COLLECTION)) {
                $existingMedia = $sourceAsset->getFirstMedia(MediaAsset::COLLECTION);
                if ($existingMedia !== null) {
                    $existingMedia->copy($sourceAsset, MediaAsset::SOURCE_COLLECTION);
                }
            }

            $sourceAsset->clearMediaCollection(MediaAsset::COLLECTION);

            $this->storageService->storeFromPath(
                $sourceAsset,
                $realPath,
                $sourceAsset->original_filename,
            );

            $sourceAsset->update([
                'annotation_layer' => $layer,
                'file_size_bytes' => $fileSize,
                'mime_type' => $mimeType,
                'status' => MediaAssetStatus::READY,
            ]);

            if ($sourceAsset->user_id !== null) {
                $this->quotaService->invalidateUsedCache($sourceAsset->user_id);
            }

            return $sourceAsset->fresh();
        });
    }
}
