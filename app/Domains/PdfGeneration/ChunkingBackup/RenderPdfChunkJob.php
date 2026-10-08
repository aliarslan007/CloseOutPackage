<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Jobs;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\PdfGeneration\DTOs\PdfChunkDTO;
use App\Domains\PdfGeneration\Services\BrowsershotPdfService;
use App\Domains\PdfGeneration\Services\PdfPhotoOptimizer;
use App\Domains\PdfGeneration\Services\PdfPhotoPageRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Renders ONE bounded chunk (a page window) as a standalone HTML document
 * through Chromium, writing its PDF to the shared chunk directory. Chunk
 * HTML carries pre-computed global page numbers, so merging the chunks in
 * order reproduces the legacy document exactly.
 */
class RenderPdfChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $timeout = 240;

    /**
     * @param  list<array{path: ?string, sourcePath: string, heading?: string, category?: string, stage?: ?string, stageHeading?: ?string, subHeading?: string, categoryId?: ?int}>  $photoItems
     */
    public function __construct(
        public readonly int $packageId,
        public readonly int $chunkIndex,
        public readonly PdfChunkDTO $chunk,
        public readonly string $token,
        public readonly int $totalPages,
        public readonly int $photoStartPage,
        public readonly string $revisionLabel,
        public readonly string $revisionDate,
        public readonly ?string $logoPath,
        public readonly ?string $partnerLogoPath,
        public readonly array $photoItems,
        public readonly int $revisionId,
    ) {}

    public function handle(): void
    {
        ini_set('memory_limit', '512M');

        /** @var CloseoutPackage $package */
        $package = CloseoutPackage::query()->findOrFail($this->packageId);
        $package->load(['siteInfo.authorUser', 'siteInfo.pmUser', 'project.manager', 'project.customer']);

        $isPhotoChunk = str_starts_with($this->chunk->id, 'photoPages');

        $optimizer = new PdfPhotoOptimizer;
        /** @var list<string> $tempPaths */
        $tempPaths = [];

        $itemPreparer = function (array $item) use ($optimizer, &$tempPaths): ?string {
            if (is_string($item['path'] ?? null) && is_file($item['path'])) {
                return $item['path'];
            }

            $optimized = $optimizer->optimizeCopy((string) $item['sourcePath']);

            if ($optimized === null) {
                return null;
            }

            $tempPaths[] = $optimized;

            return $optimized;
        };

        $startMs = (int) (hrtime(true) / 1_000_000);

        try {
            $content = $isPhotoChunk
                ? app(PdfPhotoPageRenderer::class)->renderWindow(
                    $package,
                    $this->photoItems,
                    $this->totalPages,
                    $this->revisionLabel,
                    CarbonImmutable::parse($this->revisionDate),
                    $this->logoPath,
                    $this->partnerLogoPath,
                    $this->photoStartPage,
                    $this->chunk->firstPage,
                    $this->chunk->lastPage - $this->chunk->firstPage + 1,
                    $itemPreparer,
                )
                : $this->chunk->html();

            $document = view('pdf.chunks.document', ['content' => $content])->render();
            $binary = app(BrowsershotPdfService::class)->generate($document);

            Storage::disk('private')->put($this->chunkPath(), $binary);

            $durationMs = ((int) (hrtime(true) / 1_000_000)) - $startMs;
            $peakMemoryMb = round(memory_get_peak_usage(true) / (1024 * 1024), 2);

            Log::info('PDF chunk rendered', [
                'revision_id' => $this->revisionId,
                'chunk' => $this->chunk->id,
                'pages' => $this->chunk->firstPage.'-'.$this->chunk->lastPage,
                'bytes' => strlen($binary),
                'duration_ms' => $durationMs,
                'peak_memory_mb' => $peakMemoryMb,
            ]);
        } finally {
            foreach ($tempPaths as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    private function chunkPath(): string
    {
        return 'pdfs/'.$this->packageId.'/chunks/'.$this->token.'/'.$this->chunkIndex.'.pdf';
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('PDF chunk render failed permanently', [
            'revision_id' => $this->revisionId,
            'chunk' => $this->chunk->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
