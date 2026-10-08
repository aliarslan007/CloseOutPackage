<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Actions;

use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Photo grouping is editable only while the report is still work-in-progress —
 * the same window the existing image reorder allows.
 */
final class AssertSowImagesEditableAction
{
    public function execute(SowReport $report, string $message): void
    {
        if (! in_array($report->status, [SowStatus::DRAFT, SowStatus::IN_REVIEW], true)) {
            throw new InvalidStateTransitionException($message);
        }
    }
}
