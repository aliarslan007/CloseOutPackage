<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Services\ChunkStorageService;
use App\Domains\MediaLibrary\Services\MediaQuotaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Daily hygiene for abandoned uploads (plan §11): uploads stuck in
 * processing for over 2h are marked failed silently, and chunk folders whose
 * asset row is gone (aborted/purged) are deleted from the private disk.
 */
class CleanOrphanedMediaAssetsCommand extends Command
{
    protected $signature = 'cop:cleanup-orphaned-media-assets
                            {--all : Clean all processing uploads regardless of age}
                            {--delete : Permanently purge abandoned uploads instead of marking them failed}
                            {--hours=2 : Age threshold in hours (ignored if --all is used)}';

    protected $description = 'Fail or purge abandoned media uploads and delete orphaned chunk folders';

    public function handle(ChunkStorageService $chunks, MediaQuotaService $quota): int
    {
        $query = MediaAsset::query()->where('status', MediaAssetStatus::PROCESSING->value);

        if (! $this->option('all')) {
            $hours = max(1, (int) $this->option('hours'));
            $query->where('created_at', '<', now()->subHours($hours));
        }

        $abandoned = $query->get();
        $isDelete = (bool) $this->option('delete');

        foreach ($abandoned as $asset) {
            $chunks->cleanup($asset->id);
            $userId = $asset->user_id;

            if ($isDelete) {
                $asset->forceDelete();
            } else {
                $asset->update([
                    'status' => MediaAssetStatus::FAILED,
                    'validation_error' => 'Upload abandoned',
                ]);
            }

            $quota->invalidateUsedCache($userId);
        }

        // Orphaned chunk folders: any directory whose asset is no longer actively processing
        // (completed, failed, soft-deleted, or deleted outright).
        /** @var list<int> $activeProcessingAssetIds */
        $activeProcessingAssetIds = MediaAsset::query()
            ->where('status', MediaAssetStatus::PROCESSING->value)
            ->pluck('id')
            ->all();

        $pruned = $chunks->pruneOrphanedDirectories($activeProcessingAssetIds);

        $action = $isDelete ? 'purged' : 'marked failed';
        $this->info("Cleanup completed: {$abandoned->count()} abandoned uploads {$action}, {$pruned} orphaned chunk folders removed.");

        Log::info('Orphaned media asset cleanup completed', [
            'abandoned_marked_failed' => $abandoned->count(),
            'orphaned_chunk_dirs_removed' => $pruned,
        ]);

        return self::SUCCESS;
    }
}
