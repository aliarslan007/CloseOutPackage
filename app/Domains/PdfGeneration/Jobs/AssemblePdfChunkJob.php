<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Jobs;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\PdfGeneration\Actions\EmailProgressPdfAction;
use App\Domains\PdfGeneration\Enums\PdfRevisionStatus;
use App\Domains\PdfGeneration\Events\PdfGenerationCompleted;
use App\Domains\PdfGeneration\Models\PackageRevision;
use App\Domains\PdfGeneration\Services\PdfMergeService;
use App\Shared\Exceptions\PdfRenderException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Merges the rendered chunk PDFs (in chunk order) into the final or
 * progress PDF, then finalizes the revision with the exact same output
 * conventions the legacy jobs use (final path, temp/<uuid>.pdf, events,
 * email flow).
 */
class AssemblePdfChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 240;

    public function __construct(
        public readonly int $revisionId,
        public readonly int $packageId,
        public readonly int $chunkCount,
        public readonly string $chunkDir,
        public readonly int $preparedAtMs,
        public readonly ?string $emailTo = null,
    ) {}

    public function handle(): void
    {
        ini_set('memory_limit', '512M');

        $revision = PackageRevision::query()->findOrFail($this->revisionId);
        $package = CloseoutPackage::query()->findOrFail($this->packageId);
        $disk = Storage::disk('private');

        /** @var list<string> $chunkPaths */
        $chunkPaths = [];

        for ($i = 0; $i < $this->chunkCount; $i++) {
            $path = $disk->path($this->chunkDir.'/'.$i.'.pdf');

            if (! is_file($path)) {
                throw new PdfRenderException("Chunk PDF missing: {$path}");
            }

            $chunkPaths[] = $path;
        }

        $isFinal = $revision->type->value === 'final';
        $destinationRelative = $isFinal
            ? 'pdfs/'.$package->id.'/final_R'.$package->revision_major.'_'.$package->revision_minor.'.pdf'
            : 'temp/'.Str::uuid().'.pdf';

        app(PdfMergeService::class)->merge($chunkPaths, $disk->path($destinationRelative));

        $durationMs = ((int) (hrtime(true) / 1_000_000)) - $this->preparedAtMs;

        $revision->update([
            'status' => PdfRevisionStatus::Ready,
            'storage_path' => $destinationRelative,
            'generated_at' => now(),
            'generation_duration_ms' => $durationMs,
        ]);

        if ($isFinal) {
            $package->update([
                'final_pdf_path' => $destinationRelative,
                'final_pdf_generated_at' => now(),
            ]);

            activity('media')
                ->performedOn($package)
                ->event('pdf.generation_completed')
                ->withProperties(['revision_id' => $revision->id])
                ->log('Final PDF generated');

            PdfGenerationCompleted::dispatch($revision);
        } elseif ($this->emailTo !== null) {
            // Mirrors the legacy partial-email flow: template lookups and
            // overrides resolve inside the action; a deleted template must
            // not fail delivery.
            app(EmailProgressPdfAction::class)->execute($package, $revision, $this->emailTo);
        } else {
            PdfGenerationCompleted::dispatch($revision);
        }

        $peakMemoryMb = round(memory_get_peak_usage(true) / (1024 * 1024), 2);

        Log::info('Chunked PDF assembled', [
            'revision_id' => $revision->id,
            'type' => $revision->type->value,
            'chunks' => $this->chunkCount,
            'path' => $destinationRelative,
            'duration_ms' => $durationMs,
            'peak_memory_mb' => $peakMemoryMb,
        ]);

        $this->cleanupChunks();
    }

    public function failed(?Throwable $exception): void
    {
        PackageRevision::query()
            ->whereKey($this->revisionId)
            ->update(['status' => PdfRevisionStatus::Failed->value]);

        Log::error('Chunked PDF assembly failed permanently', [
            'revision_id' => $this->revisionId,
            'error' => $exception?->getMessage(),
        ]);

        $this->cleanupChunks();
    }

    /**
     * Chunk artifacts are no longer needed once assembly has succeeded or
     * terminally failed. Explicit (never __destruct) so a retried attempt
     * cannot lose its inputs; orphaned dirs are swept by cop:cleanup-temp-pdfs.
     */
    private function cleanupChunks(): void
    {
        try {
            Storage::disk('private')->deleteDirectory($this->chunkDir);
        } catch (Throwable) {
            // The scheduled cleanup command removes leftovers.
        }
    }
}
