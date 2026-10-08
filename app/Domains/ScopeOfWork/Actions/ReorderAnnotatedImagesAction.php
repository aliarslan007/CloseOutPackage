<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Actions;

use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowAnnotatedImage;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\DB;

final class ReorderAnnotatedImagesAction
{
    /**
     * @param  list<array{id: int, sort_order: int}>  $order
     */
    public function execute(SowReport $report, array $order): void
    {
        if (! in_array($report->status, [SowStatus::DRAFT, SowStatus::IN_REVIEW], true)) {
            throw new InvalidStateTransitionException('Only draft or in-review SOWs can be reordered.');
        }

        $ids = array_column($order, 'id');

        if (count($ids) !== count(array_unique($ids))) {
            throw new InvalidStateTransitionException('Duplicate image ids in reorder payload.');
        }

        $belonging = SowAnnotatedImage::query()
            ->where('sow_report_id', $report->id)
            ->whereIn('id', $ids)
            ->count();

        if ($belonging !== count($ids)) {
            throw new InvalidStateTransitionException('Some photos do not belong to this SOW.');
        }

        DB::transaction(function () use ($order): void {
            foreach ($order as $entry) {
                SowAnnotatedImage::query()
                    ->where('id', $entry['id'])
                    ->update(['sort_order' => $entry['sort_order']]);
            }
        });
    }
}
