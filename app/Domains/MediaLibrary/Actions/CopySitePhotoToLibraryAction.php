<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Actions;

use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Services\MediaQuotaService;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Exceptions\MediaStorageUnavailableException;
use Throwable;

/**
 * Copy a project's single-file site photo into the media library as an
 * independent READY asset, so flows like SOW create can attach it without
 * re-uploading. Returns null when there is nothing attachable (no photo,
 * missing file, or a type the PDF pipeline cannot render) — callers treat
 * that as "skip the photo, keep going".
 */
final class CopySitePhotoToLibraryAction
{
    /** MIME types the SOW composite pipeline (Imagick) can render. */
    private const ATTACHABLE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private readonly MediaQuotaService $quota,
    ) {}

    public function execute(Project $project, User $creator, int $marketId): ?MediaAsset
    {
        $media = $project->getFirstMedia(Project::SITE_PHOTO_COLLECTION);

        if ($media === null || ! in_array($media->mime_type, self::ATTACHABLE_MIMES, true)) {
            return null;
        }

        $path = $media->getPath();

        if (! is_file($path)) {
            return null;
        }

        $bytes = (int) filesize($path);
        $this->quota->assertQuota($creator, $bytes);

        $asset = MediaAsset::create([
            'user_id' => $creator->id,
            'market_id' => $marketId,
            'project_id' => $project->id,
            'original_filename' => $media->file_name,
            'mime_type' => $media->mime_type,
            'file_size_bytes' => $bytes,
            'total_chunks' => 0,
            'status' => MediaAssetStatus::READY,
        ]);

        try {
            // Independent Spatie record (same pattern as copyToPhoto), so the
            // library copy survives changes to the project's site photo.
            $media->copy($asset, MediaAsset::COLLECTION);
        } catch (Throwable) {
            $asset->forceDelete();

            throw new MediaStorageUnavailableException('Unable to copy the project site photo.');
        }

        $this->quota->invalidateUsedCache($creator->id);

        return $asset;
    }
}
