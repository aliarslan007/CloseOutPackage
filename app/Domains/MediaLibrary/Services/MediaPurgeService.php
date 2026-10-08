<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Services;

use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Read model and progress store for the admin-only "delete every Media Library
 * asset" purge. Two invariants exist here to protect, and both are load-bearing:
 *
 *  1. An asset referenced by a SOW annotated image or a SOW report cover is
 *     NEVER deleted. `sow_annotated_images.media_asset_id` is ON DELETE CASCADE,
 *     so purging such an asset would silently destroy the supervisor's
 *     annotation row, and both SOW surfaces render their image from the asset
 *     instead of holding a copy. Closeout packages, photos and change-order
 *     photos need no such protection: each holds an independent copy (see
 *     DeleteMediaAssetAction, whose docblock records that guarantee).
 *
 *  2. Assets still PROCESSING are skipped. Cancelling an in-flight upload is
 *     data loss no admin asked for; `cop:cleanup-orphaned-media-assets` reclaims
 *     abandoned ones on its own schedule.
 */
final class MediaPurgeService
{
    /** Typed verbatim by the admin. Kept here so UI, request and log agree. */
    public const CONFIRMATION_PHRASE = 'DELETE ALL MEDIA';

    private const PROGRESS_KEY = 'media-library.purge.progress';

    /** A purge is only interesting while it is recent. */
    private const PROGRESS_TTL = 21_600;

    /** The dialog shows a sample; the log keeps the complete record. */
    private const MAX_REPORTED_FAILURES = 50;

    /**
     * Exactly what a purge would remove right now. Read-only.
     *
     * @return array{
     *     total_assets: int,
     *     total_bytes: int,
     *     deletable_assets: int,
     *     deletable_bytes: int,
     *     uploaders: int,
     *     protected_assets: int,
     *     protected_bytes: int,
     *     protected_reasons: array<string, int>,
     *     in_progress_assets: int,
     *     orphan_chunk_folders: int
     * }
     */
    public function preview(): array
    {
        $active = MediaAsset::query()->whereNull('deleted_at');

        /** @var object{total_assets: int|string, total_bytes: int|string, uploaders: int|string} $totals */
        $totals = (clone $active)
            ->selectRaw('COUNT(*) as total_assets, COALESCE(SUM(file_size_bytes), 0) as total_bytes, COUNT(DISTINCT user_id) as uploaders')
            ->first() ?? (object) ['total_assets' => 0, 'total_bytes' => 0, 'uploaders' => 0];

        /**
         * Deletable = referenced by NEITHER SOW surface. Both `whereNotExists`
         * calls are ANDed on purpose: `NOT annotated OR NOT cover` would let an
         * asset through whenever only one of the two references it, which is
         * exactly the SOW work this protection exists to save.
         */
        /** @var object{deletable_assets: int|string, deletable_bytes: int|string} $deletable */
        $deletable = (clone $active)
            ->where('status', '!=', MediaAssetStatus::PROCESSING->value)
            ->where(fn (Builder $q): Builder => $q
                ->whereNotExists($this->annotatedImageReference())
                ->whereNotExists($this->reportCoverReference())
                ->whereNotExists($this->duplicatePointerReference()))
            ->selectRaw('COUNT(*) as deletable_assets, COALESCE(SUM(file_size_bytes), 0) as deletable_bytes')
            ->first() ?? (object) ['deletable_assets' => 0, 'deletable_bytes' => 0];

        /** @var object{protected_assets: int|string, protected_bytes: int|string} $protected */
        $protected = (clone $active)
            ->where(fn (Builder $q): Builder => $q
                ->whereExists($this->annotatedImageReference())
                ->orWhereExists($this->reportCoverReference())
                ->orWhereExists($this->duplicatePointerReference()))
            ->selectRaw('COUNT(*) as protected_assets, COALESCE(SUM(file_size_bytes), 0) as protected_bytes')
            ->first() ?? (object) ['protected_assets' => 0, 'protected_bytes' => 0];

        $inProgress = (int) (clone $active)
            ->where('status', MediaAssetStatus::PROCESSING->value)
            ->count();

        return [
            'total_assets' => (int) $totals->total_assets,
            'total_bytes' => (int) $totals->total_bytes,
            'deletable_assets' => (int) $deletable->deletable_assets,
            'deletable_bytes' => (int) $deletable->deletable_bytes,
            'uploaders' => (int) $totals->uploaders,
            'protected_assets' => (int) $protected->protected_assets,
            'protected_bytes' => (int) $protected->protected_bytes,
            'protected_reasons' => $this->protectedReasonCounts(),
            'in_progress_assets' => $inProgress,
            'orphan_chunk_folders' => app(ChunkStorageService::class)
                ->countOrphanedDirectories($this->processingAssetIds()),
        ];
    }

    /**
     * The next page of active assets to consider, newest-first is not wanted:
     * ascending id plus a cursor guarantees the sweep always advances and always
     * terminates, even when every remaining row turns out to be protected.
     *
     * @return Collection<int, MediaAsset>
     */
    public function nextChunk(int $afterId, int $limit): Collection
    {
        return MediaAsset::query()
            ->whereNull('deleted_at')
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'user_id', 'file_size_bytes', 'status']);
    }

    /**
     * Which of the given assets must survive because SOW depends on them.
     *
     * @param  list<int>  $ids
     * @return array<int, true>
     */
    public function protectedIdsAmong(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var list<int> $annotated */
        $annotated = DB::table('sow_annotated_images')
            ->whereIn('media_asset_id', $ids)
            ->distinct()
            ->pluck('media_asset_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        /** @var list<int> $covers */
        $covers = DB::table('sow_reports')
            ->whereIn('cover_media_asset_id', $ids)
            ->distinct()
            ->pluck('cover_media_asset_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        // Canonicals whose bytes live pointers resolve through. Deleting one
        // would silently blank every SOW photo reading it.
        /** @var list<int> $canonicals */
        $canonicals = DB::table('media_assets')
            ->whereIn('duplicate_of_asset_id', $ids)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('duplicate_of_asset_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $map = [];

        foreach ([...$annotated, ...$covers, ...$canonicals] as $id) {
            $map[$id] = true;
        }

        return $map;
    }

    /**
     * Ids of uploads still in flight. Chunk folders for anything else on disk
     * are orphans and safe to remove.
     *
     * @return list<int>
     */
    public function processingAssetIds(): array
    {
        /** @var list<int> $ids */
        $ids = MediaAsset::query()
            ->where('status', MediaAssetStatus::PROCESSING->value)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return $ids;
    }

    public function isRunning(): bool
    {
        return ($this->progress()['state'] ?? null) === 'running';
    }

    /**
     * @param  array<string, mixed>|null  $progress
     * @return array<string, mixed>
     */
    public function progress(): array
    {
        $progress = Cache::get(self::PROGRESS_KEY);

        return is_array($progress) ? $progress : [];
    }

    /**
     * @param  array<string, mixed>  $counters
     * @param  array{asset_id: int, error: string}|null  $failure
     */
    public function recordChunk(array $counters, ?array $failure = null): void
    {
        $progress = $this->progress();

        if ($progress === []) {
            // Accounting was lost (cache flushed / evicted mid-purge). Losing the
            // counter is recoverable; silently under-reporting a destructive run
            // is not. Re-seed, mark the gap so the operator can see it, and carry on.
            Log::error('Media library purge lost its progress record; re-seeding accounting', [
                'pending_failure' => $failure,
            ]);

            $progress = $this->blankProgress(null, true);
        }

        foreach (['processed', 'deleted', 'deleted_bytes', 'protected_skipped', 'in_progress_skipped'] as $key) {
            $progress[$key] = (int) $progress[$key] + (int) ($counters[$key] ?? 0);
        }

        if ($failure !== null) {
            $progress['failed'] = (int) $progress['failed'] + 1;

            if (count($progress['failures']) < self::MAX_REPORTED_FAILURES) {
                $progress['failures'][] = $failure;
            }
        }

        Cache::put(self::PROGRESS_KEY, $progress, self::PROGRESS_TTL);
    }

    public function start(int $expectedTotal, User $requestedBy): void
    {
        $progress = $this->blankProgress([
            'id' => $requestedBy->id,
            'name' => $requestedBy->name,
        ], false);

        // Explicit assignment, not `+`: the union operator keeps the LEFT side
        // for duplicate keys, so `$blank + ['expected_total' => n]` silently
        // keeps the blank's 0.
        $progress['expected_total'] = $expectedTotal;

        Cache::put(self::PROGRESS_KEY, $progress, self::PROGRESS_TTL);
    }

    public function complete(int $orphanChunkFoldersRemoved): void
    {
        $progress = $this->progress();

        $progress['state'] = 'completed';
        $progress['orphan_chunk_folders_removed'] = $orphanChunkFoldersRemoved;
        $progress['finished_at'] = now()->toIso8601String();

        Cache::put(self::PROGRESS_KEY, $progress, self::PROGRESS_TTL);
    }

    private function annotatedImageReference(): QueryBuilder
    {
        return DB::table('sow_annotated_images')
            ->selectRaw('1')
            ->whereColumn('sow_annotated_images.media_asset_id', 'media_assets.id');
    }

    private function reportCoverReference(): QueryBuilder
    {
        return DB::table('sow_reports')
            ->selectRaw('1')
            ->whereColumn('sow_reports.cover_media_asset_id', 'media_assets.id');
    }

    /**
     * A canonical asset whose bytes a live duplicate pointer resolves through.
     * Purging it would leave the pointer — and any SOW holding it — resolving
     * to nothing.
     */
    private function duplicatePointerReference(): QueryBuilder
    {
        return DB::table('media_assets as pointer')
            ->selectRaw('1')
            ->whereColumn('pointer.duplicate_of_asset_id', 'media_assets.id')
            ->whereNull('pointer.deleted_at');
    }

    /**
     * Per-source-table breakdown for the dialog. An asset used both as an
     * annotation and as a cover counts in both rows, so these deliberately do
     * not sum to protected_assets.
     *
     * @return array<string, int>
     */
    private function protectedReasonCounts(): array
    {
        return [
            'annotated_images' => (int) DB::table('sow_annotated_images as sai')
                ->whereNotNull('sai.media_asset_id')
                ->whereExists(fn (QueryBuilder $q): QueryBuilder => $q->selectRaw('1')
                    ->from('media_assets')
                    ->whereColumn('media_assets.id', 'sai.media_asset_id')
                    ->whereNull('media_assets.deleted_at'))
                ->distinct()
                ->count('sai.media_asset_id'),
            'report_covers' => (int) DB::table('sow_reports')
                ->whereNotNull('cover_media_asset_id')
                ->whereExists(fn (QueryBuilder $q): QueryBuilder => $q->selectRaw('1')
                    ->from('media_assets')
                    ->whereColumn('media_assets.id', 'sow_reports.cover_media_asset_id')
                    ->whereNull('media_assets.deleted_at'))
                ->distinct()
                ->count('cover_media_asset_id'),
        ];
    }

    /**
     * @param  array{id: int, name: string}|null  $requestedBy
     * @return array<string, mixed>
     */
    private function blankProgress(?array $requestedBy, bool $accountingReset): array
    {
        return [
            'state' => 'running',
            'requested_by' => $requestedBy,
            'requested_at' => now()->toIso8601String(),
            'expected_total' => 0,
            'accounting_reset' => $accountingReset,
            'processed' => 0,
            'deleted' => 0,
            'deleted_bytes' => 0,
            'protected_skipped' => 0,
            'in_progress_skipped' => 0,
            'failed' => 0,
            'failures' => [],
            'orphan_chunk_folders_removed' => 0,
            'finished_at' => null,
        ];
    }
}
