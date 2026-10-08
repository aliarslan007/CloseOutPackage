<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Actions;

use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Requests\AppendChunkRequest;
use App\Domains\MediaLibrary\Services\ChunkStorageService;
use App\Domains\MediaLibrary\Services\UploadTokenService;
use App\Domains\Users\Models\User;
use App\Shared\Exceptions\IncompleteUploadException;
use App\Shared\Exceptions\UploadStateConflictException;
use App\Shared\Exceptions\UploadTokenInvalidException;

final class AppendChunkAction
{
    public function execute(AppendChunkRequest $request, MediaAsset $asset, User $user): void
    {
        // 1. Verify the stateless token belongs to this user and asset.
        $token = app(UploadTokenService::class)->verify($request->bearerToken() ?? '', $user->id);

        if ($token['asset_id'] !== $asset->id) {
            throw new UploadTokenInvalidException('Upload token does not belong to this asset.');
        }

        // 2. Only in-flight uploads accept chunks.
        $this->assertProcessing($asset);

        // 3. Reject indexes outside the declared chunk range.
        $chunkIndex = $request->integer('chunk_index');

        if ($chunkIndex >= $asset->total_chunks) {
            throw new IncompleteUploadException('Chunk index out of range.');
        }

        // 4. Duplicate chunk → idempotent, no re-write.
        $chunks = app(ChunkStorageService::class);

        if ($chunks->hasChunk($asset->id, $chunkIndex)) {
            return;
        }

        $chunk = $request->file('chunk');

        if ($chunk === null) {
            throw new IncompleteUploadException('No chunk file provided.');
        }

        $chunks->storeChunk($asset->id, $chunkIndex, $chunk);
    }

    private function assertProcessing(MediaAsset $asset): void
    {
        if ($asset->status === MediaAssetStatus::PROCESSING) {
            return;
        }

        throw new UploadStateConflictException(
            $asset->status === MediaAssetStatus::READY
                ? 'Upload already completed.'
                : 'Upload was cancelled or failed.',
        );
    }
}
