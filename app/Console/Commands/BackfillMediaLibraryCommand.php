<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\MediaLibrary\Actions\RegisterPhotoInLibraryAction;
use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\Photos\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * One-shot (idempotent, re-runnable) library backfill with two phases:
 *
 * 1. Hash existing READY library assets that predate the file_hash column,
 *    so content dedupe of device/camera photo uploads matches against them.
 * 2. Mirror legacy device/camera COP photos into the library through
 *    RegisterPhotoInLibraryAction — same dedupe, project link and pivot as
 *    live uploads. Only current photos (not replaced ones) are mirrored.
 *
 * Usage: php artisan media:backfill-library [--chunk=200] [--dry-run]
 */
class BackfillMediaLibraryCommand extends Command
{
    protected $signature = 'media:backfill-library {--chunk=200 : Rows scanned per chunk} {--dry-run : Report counts without writing}';

    protected $description = 'Hash existing library assets and mirror legacy COP photo uploads into the media library';

    public function handle(RegisterPhotoInLibraryAction $register): int
    {
        /** @var int $chunk */
        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $hashed = $this->hashExistingAssets($chunk, $dryRun);
        $mirrored = $this->mirrorLegacyPhotos($chunk, $dryRun, $register);

        $this->info(sprintf(
            '%s %d asset(s) hashed, %d photo(s) mirrored into the library.',
            $dryRun ? 'Would update' : 'Done:',
            $hashed,
            $mirrored,
        ));

        return self::SUCCESS;
    }

    private function hashExistingAssets(int $chunk, bool $dryRun): int
    {
        $count = 0;

        MediaAsset::query()
            ->where('status', MediaAssetStatus::READY->value)
            ->whereNull('file_hash')
            ->orderBy('id')
            ->chunkById($chunk, function ($assets) use ($dryRun, &$count): void {
                foreach ($assets as $asset) {
                    $path = $asset->getFirstMedia(MediaAsset::COLLECTION)?->getPath();

                    if ($path === null || ! is_file($path)) {
                        $this->warn("Asset {$asset->id} has no file on disk; skipped (dedupe cannot match it).");

                        continue;
                    }

                    if (! $dryRun) {
                        $asset->update(['file_hash' => hash_file('sha256', $path)]);
                    }

                    $count++;
                }
            });

        return $count;
    }

    private function mirrorLegacyPhotos(int $chunk, bool $dryRun, RegisterPhotoInLibraryAction $register): int
    {
        $count = 0;

        Photo::query()
            ->whereNull('replaced_at')
            ->whereNotExists(
                fn (QueryBuilder $q): QueryBuilder => $q->select(DB::raw(1))
                    ->from('media_asset_photos')
                    ->whereColumn('media_asset_photos.photo_id', 'photos.id')
            )
            ->with(['package.project', 'uploader'])
            ->orderBy('id')
            ->chunkById($chunk, function ($photos) use ($dryRun, $register, &$count): void {
                foreach ($photos as $photo) {
                    $media = $photo->getFirstMedia(Photo::COLLECTION);
                    $uploader = $photo->uploader;

                    if ($media === null || $uploader === null) {
                        $this->warn("Photo {$photo->id} has no stored file or uploader; skipped.");

                        continue;
                    }

                    if ($dryRun) {
                        $count++;

                        continue;
                    }

                    // Non-fatal inside the action (logged); failures just stay
                    // un-mirrored and are picked up on the next run.
                    if ($register->execute($photo, $media, $uploader, $photo->package?->project) !== null) {
                        $count++;
                    }
                }
            });

        return $count;
    }
}
