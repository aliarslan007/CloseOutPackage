<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Jobs;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\PdfGeneration\Enums\PdfType;
use App\Domains\PdfGeneration\Events\PdfGenerationCompleted;
use App\Domains\PdfGeneration\Events\PdfGenerationFailed;
use App\Domains\PdfGeneration\Models\PackageRevision;
use App\Domains\PdfGeneration\Services\BrowsershotPdfService;
use App\Domains\PdfGeneration\Services\PdfPhotoItemResolver;
use App\Domains\PdfGeneration\Services\PdfRenderService;
use App\Domains\Photos\Models\Photo;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GeneratePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly CloseoutPackage $package,
        public readonly int $generatedBy,
    ) {
        $this->onQueue('pdf');
    }

    public function handle(): void
    {
        ini_set('memory_limit', '512M');

        $startMs = (int) (hrtime(true) / 1_000_000);

        try {
            $revision = $this->generate($startMs);

            activity('media')
                ->performedOn($this->package)
                ->event('pdf.generation_completed')
                ->withProperties(['revision_id' => $revision->id])
                ->log('Final PDF generated');

            PdfGenerationCompleted::dispatch($revision);
        } catch (Throwable $exception) {
            PdfGenerationFailed::dispatch($this->package, $exception->getMessage());

            throw $exception;
        }
    }

    private function generate(int $startMs): PackageRevision
    {
        /** @var CloseoutPackage $package */
        $package = $this->package->fresh() ?? $this->package;
        $package->load(['siteInfo.authorUser', 'siteInfo.pmUser', 'project', 'checklistResponses.checklistItem', 'template.sections.categories']);

        $photos = Photo::query()
            ->where('closeout_package_id', $package->id)
            ->where('is_approved', true)
            ->with(['media', 'categorySelection.templatePictureCategory', 'stage'])
            ->get();

        $photoItems = app(PdfPhotoItemResolver::class)->resolve($photos);

        try {
            $label = 'R'.$package->revision_major.'.'.$package->revision_minor;

            /** @var CarbonImmutable|null $revisionDate */
            $revisionDate = $package->revision_date;
            $date = $revisionDate !== null ? $revisionDate->format('Y-m-d') : today()->toDateString();

            $result = app(PdfRenderService::class)->render($package, $photoItems, $label, $date);
            $binary = app(BrowsershotPdfService::class)->generate($result['html']);

            $path = 'pdfs/'.$package->id.'/final_R'.$package->revision_major.'_'.$package->revision_minor.'.pdf';
            Storage::disk('private')->put($path, $binary);

            $package->update([
                'final_pdf_path' => $path,
                'final_pdf_generated_at' => now(),
            ]);

            $durationMs = (int) (hrtime(true) / 1_000_000) - $startMs;

            return PackageRevision::create([
                'closeout_package_id' => $package->id,
                'type' => PdfType::FINAL,
                'revision_major' => $package->revision_major,
                'revision_minor' => $package->revision_minor,
                'revision_label' => $label,
                'revision_date' => $date,
                'total_pages' => $result['totalPages'],
                'storage_path' => $path,
                'generated_by' => $this->generatedBy,
                'generated_at' => now(),
                'generation_duration_ms' => $durationMs,
                'renderer_version' => $this->rendererVersion(),
            ]);
        } finally {
            foreach ($photoItems as $item) {
                if (is_file($item['path'])) {
                    unlink($item['path']);
                }
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Final PDF generation failed permanently', [
            'package_id' => $this->package->id,
            'error' => $exception?->getMessage(),
        ]);
    }

    private function rendererVersion(): ?string
    {
        $version = config('cop.renderer_version');

        return is_string($version) && $version !== '' ? $version : null;
    }
}
