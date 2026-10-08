<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Jobs;

use App\Domains\MediaLibrary\Actions\DeleteMediaAssetAction;
use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Services\ChunkStorageService;
use App\Domains\MediaLibrary\Services\MediaPurgeService;
use App\Domains\MediaLibrary\Services\MediaQuotaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Deletes every Media Library asset except the ones SOW depends on.
 *
 * Runs as a cursor sweep: each pass takes a page of active assets ordered by id,
 * soft-deletes the row (which is what makes every existing stat query — usedBytes,
 * systemUsage, statusCounts — self-correct to zero, because all three filter on
 * `deleted_at IS NULL`), then hard-deletes the stored files and conversions via
 * DeleteMediaAssetAction, exactly as DeleteProjectAction does for one project.
 * The cursor is carried into the next dispatch, so a page made entirely of
 * protected or in-flight rows still advances instead of looping forever.
 *
 * Per-asset failures are logged and counted, never swallowed: one bad row must
 * not abandon the other 5,000. Progress lives in the cache via MediaPurgeService
 * so any admin can watch it without a migration.
 */
class PurgeMediaLibraryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    /** @var list<int> */
    public array $backoff = [10, 60, 180];

    /**
     * ponytail: 100 rows per pass. Raise it if a purge of a large library needs
     * fewer passes; drop it if a pass approaches the $timeout on slow disks.
     */
    private const CHUNK_SIZE = 100;

    /** Breathing room between pages so a purge never starves the upload queue. */
    private const NEXT_PAGE_DELAY_SECONDS = 1;

    public function __construct(public readonly int $afterId = 0) {}

    public function handle(
        MediaPurgeService $purge,
        DeleteMediaAssetAction $deleteAsset,
        ChunkStorageService $chunks,
        MediaQuotaService $quota,
    ): void {
        $assets = $purge->nextChunk($this->afterId, self::CHUNK_SIZE);

        if ($assets->isEmpty()) {
            $this->finalize($purge, $chunks);

            return;
        }

        $protected = $purge->protectedIdsAmong(
            $assets->map(static fn ($asset): int => (int) $asset->id)->all()
        );

        $counters = [
            'processed' => 0,
            'deleted' => 0,
            'deleted_bytes' => 0,
            'protected_skipped' => 0,
            'in_progress_skipped' => 0,
        ];
        $failure = null;
        $touchedUserIds = [];
        $cursor = $this->afterId;

        foreach ($assets as $asset) {
            $cursor = max($cursor, (int) $asset->id);
            $counters['processed']++;

            // `status` is cast to the enum on the model, so compare to the enum.
            if ($asset->status === MediaAssetStatus::PROCESSING) {
                $counters['in_progress_skipped']++;

                continue;
            }

            if (isset($protected[(int) $asset->id])) {
                $counters['protected_skipped']++;

                continue;
            }

            try {
                // Row first: a soft-deleted asset drops out of every quota and
                // count query immediately, so the header can never report bytes
                // that are already gone.
                $asset->delete();
                $deleteAsset->releaseStoredFiles($asset);
                $chunks->cleanup((int) $asset->id);

                $counters['deleted']++;
                $counters['deleted_bytes'] += (int) $asset->file_size_bytes;
                $touchedUserIds[(int) $asset->user_id] = true;
            } catch (Throwable $exception) {
                // Logged at ERROR and reported in the progress record: the purge
                // is a bulk operation, so one unremovable row must not stop the
                // rest, and it must not vanish either.
                Log::error('Media library purge could not delete an asset', [
                    'asset_id' => $asset->id,
                    'user_id' => $asset->user_id,
                    'error' => $exception->getMessage(),
                ]);

                $failure = [
                    'asset_id' => (int) $asset->id,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        // One invalidation per distinct uploader per page, not per asset.
        foreach (array_keys($touchedUserIds) as $userId) {
            $quota->invalidateUsedCache($userId);
        }

        $purge->recordChunk($counters, $failure);

        self::dispatch($cursor)->delay(now()->addSeconds(self::NEXT_PAGE_DELAY_SECONDS));
    }

    /**
     * Last page processed: sweep the chunk folders whose asset no longer exists
     * (aborted or already-purged uploads) and close the progress record.
     */
    private function finalize(MediaPurgeService $purge, ChunkStorageService $chunks): void
    {
        $removed = $chunks->pruneOrphanedDirectories($purge->processingAssetIds());
        $purge->complete($removed);

        Log::warning('Media library purge finished', [
            'progress' => $purge->progress(),
            'orphan_chunk_folders_removed' => $removed,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Media library purge halted before completion', [
            'after_id' => $this->afterId,
            'error' => $exception?->getMessage(),
            'progress' => app(MediaPurgeService::class)->progress(),
        ]);
    }
}
