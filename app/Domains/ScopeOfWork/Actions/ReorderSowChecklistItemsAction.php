<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Actions;

use App\Domains\ScopeOfWork\Models\SowChecklistItem;
use App\Domains\ScopeOfWork\Models\SowReport;
use Illuminate\Support\Facades\DB;

final class ReorderSowChecklistItemsAction
{
    /**
     * @param  list<array{id: int, sort_order: int}>  $items
     */
    public function execute(SowReport $report, array $items): void
    {
        DB::transaction(function () use ($report, $items): void {
            foreach ($items as $entry) {
                SowChecklistItem::query()
                    ->where('sow_report_id', $report->id)
                    ->where('id', $entry['id'])
                    ->update(['sort_order' => $entry['sort_order']]);
            }
        });
    }
}
