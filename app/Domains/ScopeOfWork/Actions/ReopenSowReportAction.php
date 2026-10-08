<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Actions;

use App\Domains\ScopeOfWork\Enums\SowPdfGenerationStatus;
use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\ScopeOfWork\Notifications\SowReportReopenedNotification;
use App\Domains\Users\Models\User;
use App\Shared\Exceptions\InvalidStateTransitionException;

final class ReopenSowReportAction
{
    public function execute(SowReport $report, User $actor, ?string $reopenNotes = null): SowReport
    {
        if (! $report->status->canTransitionTo(SowStatus::DRAFT)) {
            throw new InvalidStateTransitionException('Only published or in-review SOWs can be reopened.');
        }

        if ($report->pdf_generation_status === SowPdfGenerationStatus::GENERATING) {
            throw new InvalidStateTransitionException('Wait for PDF generation to finish before reopening.');
        }

        $trimmedNotes = $reopenNotes !== null && trim($reopenNotes) !== '' ? trim($reopenNotes) : null;

        $report->update([
            'status' => SowStatus::DRAFT,
            'published_at' => null,
            'published_by' => null,
            'reopened_at' => now(),
            'reopened_by' => $actor->id,
            'reopen_notes' => $trimmedNotes,
        ]);

        $creator = $report->relationLoaded('creator') ? $report->creator : $report->creator()->first();
        if ($creator !== null && $creator->id !== $actor->id) {
            $creator->notify(new SowReportReopenedNotification($report, $actor, $trimmedNotes));
        }

        return $report;
    }
}
