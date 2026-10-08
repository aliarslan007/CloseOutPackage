<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Actions;

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderPhoto;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\Users\Models\User;
use App\Shared\Services\ImageCompressionService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Copies Media Library originals onto the private disk as self-contained
 * change-order photos. Copying (instead of linking) keeps the change order
 * intact even if the library asset is later deleted, and lets the no-auth
 * token page render it without touching MediaAssetPolicy.
 */
final class AttachChangeOrderPhotosAction
{
    public function __construct(
        private readonly ImageCompressionService $compressionService = new ImageCompressionService,
    ) {}

    /**
     * @param  list<int>  $assetIds
     * @return list<ChangeOrderPhoto> newly or already attached photos
     */
    public function execute(ChangeOrder $changeOrder, array $assetIds, User $actor): array
    {
        $disk = Storage::disk('private');
        $attached = [];

        foreach ($assetIds as $assetId) {
            /** @var MediaAsset|null $asset */
            $asset = MediaAsset::query()->find($assetId);

            if ($asset === null || ! $actor->can('view', $asset)) {
                continue;
            }

            $media = $asset->getFirstMedia(MediaAsset::COLLECTION);

            if ($media === null || ! str_starts_with((string) $media->mime_type, 'image/')) {
                continue;
            }

            $sourcePath = $media->getPath();

            if (! is_file($sourcePath)) {
                continue;
            }

            $targetPath = $this->compressionService->compressFilePath(
                $sourcePath,
                (string) $media->mime_type,
                $asset->original_filename,
            );

            $stream = fopen($targetPath, 'r');
            if ($stream === false) {
                if ($targetPath !== $sourcePath && is_file($targetPath)) {
                    @unlink($targetPath);
                }

                continue;
            }

            $extension = strtolower(pathinfo($asset->original_filename, PATHINFO_EXTENSION) ?: 'jpg');
            $path = 'change-orders/'.$changeOrder->getKey().'/'.Str::uuid().'.'.$extension;

            $written = $disk->put($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            if ($targetPath !== $sourcePath && is_file($targetPath)) {
                @unlink($targetPath);
            }

            if (! $written) {
                continue;
            }

            $photo = ChangeOrderPhoto::query()->firstOrCreate(
                [
                    'change_order_id' => $changeOrder->getKey(),
                    'media_asset_id' => $asset->getKey(),
                ],
                [
                    'uploaded_by' => $actor->getKey(),
                    'original_name' => $asset->original_filename,
                    'path' => $path,
                    'mime_type' => (string) $media->mime_type,
                    'size_bytes' => (int) ($disk->size($path) ?: $media->size),
                ],
            );

            // Dedupe hit: discard the spare copy made above.
            if (! $photo->wasRecentlyCreated && $photo->path !== $path) {
                $disk->delete($path);
            }

            $attached[] = $photo;
        }

        return $attached;
    }
}
