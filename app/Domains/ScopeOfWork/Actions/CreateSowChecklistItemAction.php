<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Actions;

use App\Domains\ScopeOfWork\Models\SowChecklistItem;
use App\Domains\ScopeOfWork\Models\SowChecklistResponse;
use App\Domains\ScopeOfWork\Models\SowReport;
use Illuminate\Support\Facades\DB;

final class CreateSowChecklistItemAction
{
    public function execute(SowReport $report, string $label, bool $hasExplainField = false, bool $isRequired = true): SowChecklistItem
    {
        return DB::transaction(function () use ($report, $label, $hasExplainField, $isRequired): SowChecklistItem {
            $maxSortOrder = SowChecklistItem::query()
                ->where('sow_report_id', $report->id)
                ->max('sort_order');
            $order = is_numeric($maxSortOrder) ? (int) $maxSortOrder : 0;

            $item = SowChecklistItem::create([
                'sow_report_id' => $report->id,
                'market_id' => $report->market_id,
                'label' => $label,
                'has_explain_field' => $hasExplainField,
                'is_required' => $isRequired,
                'sort_order' => $order + 1,
            ]);

            SowChecklistResponse::firstOrCreate(
                [
                    'sow_report_id' => $report->id,
                    'sow_checklist_item_id' => $item->id,
                ],
                [
                    'response' => null,
                    'explanation' => null,
                ]
            );

            return $item;
        });
    }
}
