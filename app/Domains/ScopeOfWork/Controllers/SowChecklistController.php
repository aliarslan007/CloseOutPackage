<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Controllers;

use App\Domains\ScopeOfWork\Models\SowChecklistResponse;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\ScopeOfWork\Requests\UpdateChecklistRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class SowChecklistController extends Controller
{
    public function update(UpdateChecklistRequest $request, SowReport $report): JsonResponse|RedirectResponse
    {
        $allowedItemIds = $report->checklistItems()->pluck('id');

        /** @var list<array<string, mixed>> $responses */
        $responses = $request->validated('responses') ?? [];

        foreach ($responses as $entry) {
            $rawId = $entry['sow_checklist_item_id'] ?? null;
            $itemId = is_numeric($rawId) ? (int) $rawId : 0;

            if (! $allowedItemIds->contains($itemId)) {
                continue;
            }

            $responseVal = is_string($entry['response'] ?? null) ? $entry['response'] : null;
            $rawExplanation = is_string($entry['explanation'] ?? null) ? $entry['explanation'] : null;
            $explanationVal = $responseVal === 'no' ? $rawExplanation : null;

            SowChecklistResponse::query()
                ->updateOrCreate(
                    [
                        'sow_report_id' => $report->id,
                        'sow_checklist_item_id' => $itemId,
                    ],
                    [
                        'response' => $responseVal,
                        'explanation' => $explanationVal,
                    ]
                );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'checklist' => $report->checklist(),
            ]);
        }

        return redirect()->back()->with('success', 'Survey checklist saved.');
    }
}
