<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Actions;

use App\Domains\ScopeOfWork\Enums\SowPdfGenerationStatus;
use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Jobs\GenerateSowPdfJob;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\ScopeOfWork\Notifications\SowReportPublishedNotification;
use App\Domains\Users\Models\User;
use App\Shared\Exceptions\InvalidStateTransitionException;

final class PublishSowReportAction
{
    public function execute(SowReport $report, User $actor): SowReport
    {
        if (! $report->status->canTransitionTo(SowStatus::PUBLISHED)) {
            throw new InvalidStateTransitionException('Only draft or in-review SOWs can be finalized.');
        }

        $wasReopened = $report->reopened_at !== null;
        $hasRevisions = $report->pdfRevisions()->exists();

        $updateData = [
            'status' => SowStatus::PUBLISHED,
            'published_at' => now(),
            'published_by' => $actor->id,
            'pdf_generation_status' => SowPdfGenerationStatus::GENERATING,
        ];

        if ($wasReopened && $hasRevisions) {
            $updateData['revision'] = max(1, (int) $report->revision) + 1;
            $updateData['revision_date'] = today();
            $updateData['reopened_at'] = null;
        }

        $report->update($updateData);

        GenerateSowPdfJob::dispatch($report, $actor->id);

        $creator = $report->relationLoaded('creator') ? $report->creator : $report->creator()->first();
        if ($creator !== null && $creator->id !== $actor->id) {
            $creator->notify(new SowReportPublishedNotification($report, $actor));
        }

        return $report;
    }
}
