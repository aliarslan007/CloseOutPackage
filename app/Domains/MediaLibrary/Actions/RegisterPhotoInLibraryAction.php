<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Actions;

use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Models\MediaAssetPhoto;
use App\Domains\MediaLibrary\Services\MediaQuotaService;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Mirrors a device/camera-uploaded COP photo into the media library as an
 * independent READY asset, so every image a technician or manager captures
 * becomes library-visible and project-linked — not just the ones dragged
 * from the library picker.
 *
 * Deduplicates by SHA-256 of the stored bytes: an identical image already in
 * the library is reused (one physical copy, one asset row) instead of being
 * stored twice. The photo↔asset pivot is recorded either way.
 *
 * Deliberately non-fatal: COP creation must never fail because the library
 * mirror could not be written (quota, disk). Storage problems are logged and
 * the asset simply is not created; `media:backfill-library` retries later.
 */
final class RegisterPhotoInLibraryAction
{
    public function __construct(
        private readonly MediaQuotaService $quota,
    ) {}

    public function execute(Photo $photo, Media $media, User $uploader, ?Project $project): ?MediaAsset
    {
        $path = $media->getPath();

        if (! is_file($path)) {
            Log::warning('Media library photo mirror skipped: stored file missing', [
                'photo_id' => $photo->id,
                'media_id' => $media->id,
            ]);

            return null;
        }

        $hash = hash_file('sha256', $path);

        if ($hash === false) {
            return null; // File vanished between is_file() and hash_file().
        }

        $asset = MediaAsset::query()
            ->where('file_hash', $hash)
            ->where('status', MediaAssetStatus::READY->value)
            ->first() ?? $this->createAsset($photo, $media, $hash, $uploader, $project);

        if ($asset !== null) {
            MediaAssetPhoto::query()->firstOrCreate([
                'media_asset_id' => $asset->id,
                'photo_id' => $photo->id,
            ]);
        }

        return $asset;
    }

    private function createAsset(
        Photo $photo,
        Media $media,
        string $hash,
        User $uploader,
        ?Project $project,
    ): ?MediaAsset {
        $bytes = (int) filesize($media->getPath());
        $asset = null;

        try {
            $this->quota->assertQuota($uploader, $bytes);

            $asset = MediaAsset::create([
                'user_id' => $uploader->id,
                'market_id' => $project?->market_id,
                'project_id' => $project?->id,
                'original_filename' => $media->file_name,
                'mime_type' => $media->mime_type,
                'file_size_bytes' => $bytes,
                'total_chunks' => 0,
                'gps_latitude' => $photo->gps_latitude,
                'gps_longitude' => $photo->gps_longitude,
                'gps_accuracy' => $photo->gps_accuracy,
                'captured_at' => $photo->captured_at,
                'status' => MediaAssetStatus::READY,
                'file_hash' => $hash,
            ]);

            // Independent Spatie record (same pattern as CopySitePhotoToLibrary),
            // so deleting the package photo never destroys the library asset.
            $media->copy($asset, MediaAsset::COLLECTION);
        } catch (Throwable $exception) {
            $asset?->forceDelete();

            // Non-fatal by design — see class docblock.
            Log::error('Media library photo mirror failed', [
                'photo_id' => $photo->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        $this->quota->invalidateUsedCache($uploader->id);

        return $asset;
    }
}
