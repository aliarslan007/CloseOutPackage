<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Actions;

use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Shared\Exceptions\InvalidStateTransitionException;

final class UpdateSowReportAction
{
    /**
     * Updates only the submitted columns — an absent key leaves the stored value untouched,
     * a null value clears it. This keeps `cover_media_asset_id` (also written by the image
     * strip) safe when the Project/Survey data forms save.
     *
     * @param  array<string, mixed>  $data  validated request fields
     */
    public function execute(SowReport $report, array $data): SowReport
    {
        if (! in_array($report->status, [SowStatus::DRAFT, SowStatus::IN_REVIEW], true)) {
            throw new InvalidStateTransitionException('Only draft or in-review SOWs can be edited.');
        }

        $report->update($data);

        return $report;
    }
}
