<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Actions;

use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Jobs\ProcessMediaAssetJob;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Requests\FinalizeUploadRequest;
use App\Domains\MediaLibrary\Services\ChunkStorageService;
use App\Domains\MediaLibrary\Services\UploadTokenService;
use App\Domains\Users\Models\User;
use App\Shared\Exceptions\IncompleteUploadException;
use App\Shared\Exceptions\UploadStateConflictException;
use App\Shared\Exceptions\UploadTokenInvalidException;
use Illuminate\Support\Facades\DB;

final class FinalizeUploadAction
{
    public function execute(FinalizeUploadRequest $request, MediaAsset $asset, User $user): MediaAsset
    {
        // 1. Verify the stateless token before touching the asset.
        $token = app(UploadTokenService::class)->verify($request->bearerToken() ?? '', $user->id);

        if ($token['asset_id'] !== $asset->id) {
            throw new UploadTokenInvalidException('Upload token does not belong to this asset.');
        }

        // 2. Lock the row so concurrent finalizes cannot double-dispatch.
        DB::transaction(function () use ($asset): void {
            $locked = MediaAsset::query()->lockForUpdate()->findOrFail($asset->id);

            if ($locked->status !== MediaAssetStatus::PROCESSING) {
                throw new UploadStateConflictException(
                    $locked->status === MediaAssetStatus::READY
                        ? 'Upload already completed.'
                        : 'Upload was cancelled or failed.',
                );
            }

            // 3. All declared chunks must be on disk before processing starts.
            $received = app(ChunkStorageService::class)->countChunks($locked->id);

            if ($received < $locked->total_chunks) {
                throw new IncompleteUploadException("{$received} of {$locked->total_chunks} parts received.");
            }

            // 4. Processing continues async on the default queue.
            ProcessMediaAssetJob::dispatch($locked->id);
        });

        return $asset->refresh();
    }
}
