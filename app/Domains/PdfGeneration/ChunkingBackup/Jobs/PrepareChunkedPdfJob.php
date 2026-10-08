<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Jobs;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\PdfGeneration\DTOs\PdfChunkDTO;
use App\Domains\PdfGeneration\Enums\PdfRevisionStatus;
use App\Domains\PdfGeneration\Enums\PdfType;
use App\Domains\PdfGeneration\Events\PdfGenerationFailed;
use App\Domains\PdfGeneration\Models\PackageRevision;
use App\Domains\PdfGeneration\Services\PdfChunkPlanner;
use App\Domains\PdfGeneration\Services\PdfPhotoItemResolver;
use App\Domains\Photos\Models\Photo;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Entry point of the chunked pipeline: resolves photo metadata, plans the
 * page chunks, creates the pending revision, and fans out one render job
 * per chunk plus the final assemble job on a Bus batch.
 */
class PrepareChunkedPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(
        public readonly CloseoutPackage $package,
        public readonly PdfType $type,
        public readonly ?int $generatedBy = null,
        public readonly ?string $emailTo = null,
    ) {
        $this->onQueue('pdf');
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('pdf:package:'.$this->package->id))->expireAfter(600),
        ];
    }

    public function handle(): void
    {
        ini_set('memory_limit', '512M');

        /** @var CloseoutPackage $package */
        $package = $this->package->fresh() ?? $this->package;

        $photos = Photo::query()
            ->where('closeout_package_id', $package->id)
            ->when($this->type === PdfType::FINAL, fn ($query) => $query->where('is_approved', true))
            ->with(['media', 'categorySelection.templatePictureCategory', 'stage'])
            ->get();

        // Metadata only: optimization happens lazily inside each chunk job.
        $photoItems = app(PdfPhotoItemResolver::class)->resolve($photos, optimize: false);

        $label = 'R'.$package->revision_major.'.'.$package->revision_minor;

        /** @var CarbonImmutable|null $revisionDate */
        $revisionDate = $package->revision_date;
        $date = $revisionDate !== null ? $revisionDate->format('Y-m-d') : today()->toDateString();

        /** @var array{chunks: list<PdfChunkDTO>, totalPages: int, photoStartPage: int, logoPath: ?string, partnerLogoPath: ?string} $plan */
        $plan = app(PdfChunkPlanner::class)->plan($package, $photoItems, $label, $date);
        $chunks = $plan['chunks'];
        $photoStartPage = $plan['photoStartPage'];

        $token = (string) Str::uuid();
        $chunkDir = 'pdfs/'.$package->id.'/chunks/'.$token;
        $totalPages = count($chunks) === 0 ? 0 : end($chunks)->lastPage;
        $preparedAtMs = (int) (hrtime(true) / 1_000_000);

        $revision = PackageRevision::create([
            'closeout_package_id' => $package->id,
            'type' => $this->type,
            'status' => PdfRevisionStatus::Pending,
            'revision_major' => $package->revision_major,
            'revision_minor' => $package->revision_minor,
            'revision_label' => $label,
            'revision_date' => $date,
            'total_pages' => $totalPages,
            'photo_count' => count($photoItems),
            'storage_path' => null,
            'generated_by' => $this->type === PdfType::FINAL ? $this->generatedBy : null,
            'batch_id' => $token,
        ]);

        $jobs = [];

        foreach ($chunks as $index => $chunk) {
            $jobs[] = new RenderPdfChunkJob(
                packageId: $package->id,
                chunkIndex: $index,
                chunk: $chunk,
                token: $token,
                totalPages: $plan['totalPages'],
                photoStartPage: $photoStartPage,
                revisionLabel: $label,
                revisionDate: $date,
                logoPath: $plan['logoPath'],
                partnerLogoPath: $plan['partnerLogoPath'],
                photoItems: $photoItems,
                revisionId: $revision->id,
            );
        }

        $jobs[] = new AssemblePdfChunkJob(
            revisionId: $revision->id,
            packageId: $package->id,
            chunkCount: count($chunks),
            chunkDir: $chunkDir,
            preparedAtMs: $preparedAtMs,
            emailTo: $this->emailTo,
        );

        $revisionId = $revision->id;
        $packageId = $package->id;

        Bus::batch($jobs)
            ->onQueue('pdf')
            ->name('cop-pdf-'.$package->id.'-'.$token)
            ->catch(function (array $batchJobs, Throwable $exception) use ($revisionId, $packageId): void {
                $this->failPipeline($revisionId, $packageId, $exception);
            })
            ->dispatch();

        Log::info('Chunked PDF pipeline dispatched', [
            'package_id' => $package->id,
            'revision_id' => $revisionId,
            'chunks' => count($chunks),
            'total_pages' => $totalPages,
            'photos' => count($photoItems),
        ]);
    }

    private function failPipeline(int $revisionId, int $packageId, Throwable $exception): void
    {
        PackageRevision::query()
            ->whereKey($revisionId)
            ->update(['status' => PdfRevisionStatus::Failed->value]);

        /** @var CloseoutPackage|null $package */
        $package = CloseoutPackage::query()->find($packageId);

        if ($package !== null) {
            PdfGenerationFailed::dispatch($package, $exception->getMessage());
        }

        Log::error('Chunked PDF pipeline failed', [
            'revision_id' => $revisionId,
            'error' => $exception->getMessage(),
        ]);
    }
}
