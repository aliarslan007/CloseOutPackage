<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Actions;

use App\Domains\PdfGeneration\Services\BrowsershotPdfService;
use App\Domains\ScopeOfWork\Enums\SowPdfGenerationStatus;
use App\Domains\ScopeOfWork\Models\SowPdfRevision;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\ScopeOfWork\Services\SowPdfRenderService;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Storage;

final class GenerateSowPdfAction
{
    public function __construct(
        private readonly SowPdfRenderService $renderService,
        private readonly BrowsershotPdfService $browsershotService,
    ) {}

    public function execute(SowReport $report, User $actor): string
    {
        $revisionNumber = max(1, (int) $report->revision);
        $disk = Storage::disk('private');

        // Always rebuild: the rendered PDF carries the current date, so a
        // cached file from an earlier run would show a stale date.
        $report->update(['pdf_generation_status' => SowPdfGenerationStatus::GENERATING]);

        try {
            $rendered = $this->renderService->render($report);
            $binary = $this->browsershotService->generate($rendered['html']);

            $path = "sow_pdfs/{$report->id}/sow_report_r{$revisionNumber}.pdf";
            $disk->put($path, $binary);

            SowPdfRevision::updateOrCreate(
                [
                    'sow_report_id' => $report->id,
                    'revision_number' => $revisionNumber,
                ],
                [
                    'revision_date' => today(),
                    'storage_disk' => 'private',
                    'storage_path' => $path,
                    'generated_by' => $actor->id,
                ]
            );

            $report->update([
                'pdf_generation_status' => SowPdfGenerationStatus::READY,
            ]);

            return $path;
        } catch (\Throwable $e) {
            $report->update(['pdf_generation_status' => SowPdfGenerationStatus::FAILED]);

            throw $e;
        }
    }
}
