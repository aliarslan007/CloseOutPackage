<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Actions;

use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Shared\Exceptions\InvalidStateTransitionException;

final class UnarchiveSowReportAction
{
    public function execute(SowReport $report): SowReport
    {
        if ($report->status !== SowStatus::ARCHIVED) {
            throw new InvalidStateTransitionException('Only archived SOWs can be unarchived.');
        }

        $targetStatus = match (true) {
            $report->published_at !== null => SowStatus::PUBLISHED,
            $report->submitted_at !== null => SowStatus::IN_REVIEW,
            default => SowStatus::DRAFT,
        };

        if (! $report->status->canTransitionTo($targetStatus)) {
            throw new InvalidStateTransitionException('Cannot transition SOW from archived to '.$targetStatus->value);
        }

        $report->update(['status' => $targetStatus]);

        return $report;
    }
}
