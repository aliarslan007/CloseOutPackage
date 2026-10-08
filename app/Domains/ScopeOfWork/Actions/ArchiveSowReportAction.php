<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Actions;

use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Shared\Exceptions\InvalidStateTransitionException;

/** PUBLISHED → ARCHIVED — terminal state, admin only (role enforced by SowReportPolicy). */
final class ArchiveSowReportAction
{
    public function execute(SowReport $report): SowReport
    {
        if (! $report->status->canTransitionTo(SowStatus::ARCHIVED)) {
            throw new InvalidStateTransitionException('Only in-review or published SOWs can be archived.');
        }

        $report->update(['status' => SowStatus::ARCHIVED]);

        return $report;
    }
}
