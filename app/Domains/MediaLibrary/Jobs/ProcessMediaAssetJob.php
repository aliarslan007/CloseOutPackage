<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Jobs;

use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Events\MediaAssetProcessingFailed;
use App\Domains\MediaLibrary\Events\MediaAssetUploaded;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Services\ChunkStorageService;
use App\Domains\MediaLibrary\Services\MediaAssetStorageService;
use App\Domains\MediaLibrary\Services\MediaAssetValidationService;
use App\Domains\MediaLibrary\Services\MediaQuotaService;
use App\Domains\Photos\Services\GpsValidationService;
use App\Domains\Users\Models\User;
use App\Shared\Exceptions\FileSizeMismatchException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reassembles the uploaded chunks, deep-validates the file (magic bytes +
 * decode; HEIC is converted to JPEG), stores it via Spatie Media Library and
 * marks the asset ready. Retried 3 times with backoff; permanent failures
 * mark the asset failed and notify the uploader via MediaAssetProcessingFailed.
 */
class ProcessMediaAssetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $assetId) {}

    public function handle(
        ChunkStorageService $chunks,
        MediaAssetStorageService $storage,
        MediaQuotaService $quota,
        MediaAssetValidationService $validation,
    ): void {
        $asset = MediaAsset::query()->find($this->assetId);

        // Aborted/deleted uploads have no row left; ignore the dispatch.
        if ($asset === null) {
            return;
        }

        // Idempotent: a duplicate dispatch for an already-ready asset stops here.
        if ($asset->status === MediaAssetStatus::READY) {
            return;
        }

        // A permanently-failed asset must not be resurrected by a stray
        // dispatch — flag it so operators can investigate.
        if ($asset->status === MediaAssetStatus::FAILED) {
            Log::warning('ProcessMediaAssetJob dispatched for a permanently-failed asset', [
                'asset_id' => $this->assetId,
            ]);

            return;
        }

        // 1. Reassemble chunks into a contiguous temp file.
        $tempPath = $chunks->reassemble($this->assetId);
        $convertedPath = null;

        try {
            // 2. Assembled size must match the declared size (1 KB tolerance).
            $actualBytes = (int) filesize($tempPath);

            if (abs($actualBytes - $asset->file_size_bytes) > 1024) {
                throw new FileSizeMismatchException('Assembled file size does not match declared size.');
            }

            // 3. Deep MIME validation (magic bytes + decode). HEIC/HEIF is
            //    converted to JPEG, yielding a possibly different file path.
            $validated = $validation->assertValidImage($tempPath, $asset->mime_type);
            $filePath = $validated['path'];

            if ($filePath !== $tempPath) {
                $convertedPath = $filePath;
            }

            // 4. Quota re-check on the actual bytes. Only meaningful when the
            //    file came out larger than declared — on the happy path the
            //    delta is zero and the initiate pre-check already passed.
            $delta = max(0, $actualBytes - $asset->file_size_bytes);

            if ($delta > 0) {
                $owner = User::query()->findOrFail($asset->user_id);
                $quota->assertQuota($owner, $delta);
            }

            // 5. EXIF GPS extraction (non-fatal) — read from the original
            //    bytes, before any HEIC→JPEG conversion strips metadata.
            $this->extractExifGps($asset, $tempPath);

            // 6. Persist the effective MIME type (HEIC uploads become JPEG).
            if ($asset->mime_type !== $validated['mime_type']) {
                $asset->update(['mime_type' => $validated['mime_type']]);
            }

            // 7. Store via Spatie; thumb + medium conversions are registered
            //    on the model and generated lazily on first request. Hash the
            //    effective bytes first — storing may consume the temp file.
            $hash = hash_file('sha256', $filePath);

            // These exact bytes are already in the library. Never store a
            // second copy and never fail the upload — demote this row to a
            // pointer and let MediaAsset::getFirstMedia resolve the bytes.
            $existing = MediaAsset::query()
                ->where('file_hash', $hash)
                ->where('status', MediaAssetStatus::READY->value)
                ->whereNull('duplicate_of_asset_id')
                ->first();

            if ($existing !== null) {
                $asset->update([
                    'status' => MediaAssetStatus::READY,
                    'file_hash' => $hash,
                    'duplicate_of_asset_id' => $existing->id,
                ]);
                $chunks->cleanup($this->assetId);
                $this->removeTemp($tempPath);
                $this->removeTemp($convertedPath);

                // No quota change: a pointer stores no bytes.
                MediaAssetUploaded::dispatch($asset);

                return;
            }

            $storage->storeFromPath($asset, $filePath, $asset->original_filename);

            // 8. Mark ready (+ content hash, so device photo uploads can
            // dedupe against this asset) + invalidate the used-bytes cache
            // + notify.
            $asset->update([
                'status' => MediaAssetStatus::READY,
                'file_hash' => $hash,
            ]);
            $quota->invalidateUsedCache($asset->user_id);
            MediaAssetUploaded::dispatch($asset);
        } catch (Throwable $exception) {
            $this->removeTemp($tempPath);
            $this->removeTemp($convertedPath);

            throw $exception; // Chunks are kept so the job can retry.
        }

        // Success — drop the temp files and the consumed chunks.
        $chunks->cleanup($this->assetId);
        $this->removeTemp($tempPath);
        $this->removeTemp($convertedPath);
    }

    public function failed(?Throwable $exception): void
    {
        MediaAsset::query()
            ->where('id', $this->assetId)
            ->whereNull('deleted_at')
            ->update([
                'status' => MediaAssetStatus::FAILED,
                'validation_error' => mb_substr((string) ($exception?->getMessage() ?? 'Processing failed.'), 0, 490),
            ]);

        Log::error('Media asset processing failed permanently', [
            'asset_id' => $this->assetId,
            'error' => $exception?->getMessage(),
        ]);

        MediaAssetProcessingFailed::dispatch(
            $this->assetId,
            mb_substr((string) ($exception?->getMessage() ?? 'Processing failed.'), 0, 490),
        );
    }

    private function extractExifGps(MediaAsset $asset, string $path): void
    {
        // Keep GPS captured at initiate when present; only fill from EXIF.
        if ($asset->gps_latitude !== null || $asset->gps_longitude !== null) {
            return;
        }

        try {
            $file = new UploadedFile($path, $asset->original_filename, $asset->mime_type, null, true);
            $gps = app(GpsValidationService::class)->fromFile($file);
        } catch (Throwable) {
            return; // EXIF is best-effort; never fail the upload over it.
        }

        if ($gps['latitude'] === null || $gps['longitude'] === null) {
            return;
        }

        $asset->update([
            'gps_latitude' => $gps['latitude'],
            'gps_longitude' => $gps['longitude'],
            'gps_accuracy' => $gps['accuracy'],
        ]);
    }

    private function removeTemp(?string $path): void
    {
        if ($path !== null && is_file($path)) {
            unlink($path);
        }
    }
}
