<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupTempPdfsCommand extends Command
{
    protected $signature = 'cop:cleanup-temp-pdfs';

    protected $description = 'Delete temporary PDF files and orphaned optimized photos older than the configured TTL';

    /**
     * Prefix used by PdfPhotoItemResolver for its optimized photo copies
     * written to the system temp directory. These leak when a queue worker
     * is killed mid-generation (the job's finally block never runs), so they
     * are swept here alongside the temp PDFs.
     */
    private const PHOTO_TEMP_PREFIX = 'cop_photo_';

    public function handle(): int
    {
        /** @var int $ttlHours */
        $ttlHours = config('cop.temp_pdf_ttl_hours', 24);
        $cutoff = now()->subHours($ttlHours)->getTimestamp();
        $deleted = $this->deleteTempPdfs($cutoff);
        $deletedPhotos = $this->deleteOrphanedOptimizedPhotos($cutoff);

        Log::info('Temp PDF cleanup completed', [
            'deleted' => $deleted,
            'deleted_photos' => $deletedPhotos,
        ]);

        activity('system')
            ->event('pdf.temp_cleanup')
            ->withProperties([
                'deleted' => $deleted,
                'deleted_photos' => $deletedPhotos,
            ])
            ->log('Temp PDF cleanup completed');

        return self::SUCCESS;
    }

    private function deleteTempPdfs(int $cutoff): int
    {
        $deleted = 0;

        /** @var list<string> $files */
        $files = Storage::disk('private')->files('temp');

        foreach ($files as $file) {
            $modifiedAt = Storage::disk('private')->lastModified($file);

            if ($modifiedAt < $cutoff) {
                Storage::disk('private')->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    private function deleteOrphanedOptimizedPhotos(int $cutoff): int
    {
        $deleted = 0;
        $pattern = sys_get_temp_dir().DIRECTORY_SEPARATOR.self::PHOTO_TEMP_PREFIX.'*';

        /** @var list<string> $files */
        $files = glob($pattern) ?: [];

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) !== false && filemtime($file) < $cutoff) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }
}
