<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Photos\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Backfills the upload-time `pdf` conversion (Phase 3) for legacy photo
 * media that predates its registration. Idempotent: only media missing the
 * generated conversion file is re-queued through Spatie's FileManipulator
 * with onlyMissing=true. Queued conversions land on the media conversions
 * queue (MEDIA_QUEUE) — never the `pdf` render queue.
 *
 * Usage: php artisan cop:backfill-photo-conversions [--chunk=200] [--dry-run]
 */
class BackfillPhotoPdfConversionsCommand extends Command
{
    protected $signature = 'cop:backfill-photo-conversions {--chunk=200 : Number of media rows scanned per chunk} {--dry-run : Report counts without dispatching}';

    protected $description = 'Queue missing pdf conversions for legacy photo media';

    public function handle(FileManipulator $manipulator): int
    {
        /** @var int $chunk */
        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $queued = 0;
        $skipped = 0;

        Media::query()
            ->where('model_type', (new Photo)->getMorphClass())
            ->where('collection_name', Photo::COLLECTION)
            ->orderBy('id')
            ->chunkById($chunk, function ($medias) use ($manipulator, $dryRun, &$queued, &$skipped): void {
                foreach ($medias as $media) {
                    // hasGeneratedConversion() trusts the generated_conversions
                    // payload; also verify the file exists (failed deletes /
                    // manual purges leave the flag true with no file on disk).
                    if ($media->hasGeneratedConversion('pdf') && is_file($media->getPath('pdf'))) {
                        $skipped++;

                        continue;
                    }

                    if (! $dryRun) {
                        $manipulator->createDerivedFiles($media, ['pdf'], true, false);
                    }

                    $queued++;
                }
            });

        Log::info('Photo pdf conversion backfill completed', [
            'queued' => $queued,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
        ]);

        $this->info("Queued {$queued} missing pdf conversions ({$skipped} already present).");

        return self::SUCCESS;
    }
}
