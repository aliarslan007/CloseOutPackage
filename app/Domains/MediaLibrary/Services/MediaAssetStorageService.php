<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Services;

use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\Photos\Models\Photo;
use App\Shared\Exceptions\MediaFileMissingException;
use App\Shared\Exceptions\MediaStorageUnavailableException;
use App\Shared\Services\ImageCompressionService;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Moves an assembled upload into Spatie Media Library as an independent
 * Media record on the MediaAsset (thumb + medium conversions registered on
 * the model). Failures bubble up to the processing job, which marks the
 * asset as failed.
 */
final class MediaAssetStorageService
{
    public function storeFromPath(MediaAsset $asset, string $absolutePath, string $originalFilename): Media
    {
        // Spatie infers MIME from the file name, which extension spoofing can
        // lie about — keep the server-validated declaration for Phase 2's
        // deep check and for later serving decisions.
        return $asset
            ->addMedia($absolutePath)
            ->usingFileName($originalFilename)
            ->withCustomProperties(['declared_mime' => $asset->mime_type])
            ->toMediaCollection(MediaAsset::COLLECTION);
    }

    /**
     * Copy the asset's stored file onto a freshly-created COP Photo as an
     * independent Spatie Media record. The two records never share rows, so
     * deleting the library asset later leaves the assigned Photo untouched.
     *
     * The COP copy is a *duplicate*, so it is compressed (≤2560px / ≤3MB) just
     * like a device upload. The Media Library master stays byte-for-byte.
     */
    public function copyToPhoto(MediaAsset $asset, Photo $photo): Media
    {
        return $this->copyToCollection($asset, $photo, Photo::COLLECTION);
    }

    /**
     * Copy a library asset's stored file onto any COP model collection as an
     * independent duplicate, compressing oversized masters on the way. Used by
     * photo assignment and package site-note images. The two records never
     * share rows, so deleting the library asset later leaves the copy intact.
     */
    public function copyToCollection(MediaAsset $asset, HasMedia $model, string $collection): Media
    {
        $source = $asset->getFirstMedia(MediaAsset::COLLECTION);

        if ($source === null) {
            throw new MediaFileMissingException('This photo has no stored file. Please re-upload it.');
        }

        try {
            return $this->compressedCopy($source, $model, $collection)
                ?? $source->copy($model, $collection);
        } catch (MediaFileMissingException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new MediaStorageUnavailableException('Unable to copy the photo. Please try again.');
        }
    }

    /**
     * Compressed copy of a library master, or null when the master is already
     * within limits (or GD could not process it — fail-safe to a raw copy).
     */
    private function compressedCopy(Media $source, HasMedia $model, string $collection): ?Media
    {
        try {
            $sourcePath = $source->getPath();

            if (! is_file($sourcePath)) {
                return null;
            }

            $compressedPath = app(ImageCompressionService::class)->compressFilePath(
                $sourcePath,
                (string) $source->mime_type,
                (string) $source->file_name,
            );

            if ($compressedPath === $sourcePath) {
                return null;
            }

            return $model
                ->addMedia($compressedPath)
                ->usingFileName((string) $source->file_name)
                ->toMediaCollection($collection);
        } catch (Throwable $exception) {
            // Compression is an optimization, never a blocker for the assign.
            Log::warning('Library-to-COP copy stored uncompressed', [
                'media_id' => $source->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
