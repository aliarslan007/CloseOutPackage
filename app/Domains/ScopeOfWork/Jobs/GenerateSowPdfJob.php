<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Jobs;

use App\Domains\ScopeOfWork\Actions\GenerateSowPdfAction;
use App\Domains\ScopeOfWork\Enums\SowPdfGenerationStatus;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\ScopeOfWork\Notifications\SowPdfReadyNotification;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GenerateSowPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly SowReport $report,
        public readonly int $generatedBy,
    ) {}

    public function handle(GenerateSowPdfAction $generatePdfAction): void
    {
        ini_set('memory_limit', '512M');

        /** @var SowReport $report */
        $report = $this->report->fresh() ?? $this->report;
        $actor = User::find($this->generatedBy) ?? $report->creator;

        if ($actor === null) {
            // Clear the generating flag: nothing else would, leaving the UI stuck forever.
            $report->update(['pdf_generation_status' => SowPdfGenerationStatus::FAILED]);
            Log::error('SOW PDF generation job missing actor and creator', ['report_id' => $report->id]);

            return;
        }

        try {
            $generatePdfAction->execute($report, $actor);

            $report->refresh();
            $rev = max(1, (int) $report->revision);

            $creator = $report->creator;
            $creator?->notify(new SowPdfReadyNotification($report, $rev));

            if ($actor->id !== $creator?->id) {
                $actor->notify(new SowPdfReadyNotification($report, $rev));
            }
        } catch (Throwable $e) {
            $report->update(['pdf_generation_status' => SowPdfGenerationStatus::FAILED]);
            Log::error('SOW PDF generation job failed', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->report->update(['pdf_generation_status' => SowPdfGenerationStatus::FAILED]);
        Log::error('SOW PDF generation job failed permanently', [
            'report_id' => $this->report->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
