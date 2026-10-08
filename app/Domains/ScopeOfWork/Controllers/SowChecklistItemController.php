<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Controllers;

use App\Domains\ScopeOfWork\Actions\CreateSowChecklistItemAction;
use App\Domains\ScopeOfWork\Actions\DeleteSowChecklistItemAction;
use App\Domains\ScopeOfWork\Actions\ReorderSowChecklistItemsAction;
use App\Domains\ScopeOfWork\Actions\UpdateSowChecklistItemAction;
use App\Domains\ScopeOfWork\Models\SowChecklistItem;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\ScopeOfWork\Requests\ReorderSowChecklistItemsRequest;
use App\Domains\ScopeOfWork\Requests\StoreSowChecklistItemRequest;
use App\Domains\ScopeOfWork\Requests\UpdateSowChecklistItemRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SowChecklistItemController extends Controller
{
    public function __construct(
        private readonly CreateSowChecklistItemAction $createAction,
        private readonly UpdateSowChecklistItemAction $updateAction,
        private readonly DeleteSowChecklistItemAction $deleteAction,
        private readonly ReorderSowChecklistItemsAction $reorderAction,
    ) {}

    public function store(StoreSowChecklistItemRequest $request, SowReport $report): JsonResponse|RedirectResponse
    {
        $label = $request->string('label')->toString();
        $hasExplainField = (bool) ($request->validated('has_explain_field') ?? false);
        $isRequired = (bool) ($request->validated('is_required') ?? true);

        $item = $this->createAction->execute($report, $label, $hasExplainField, $isRequired);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'item' => $item,
            ]);
        }

        return redirect()->back()->with('success', 'Checklist question added.');
    }

    public function update(
        UpdateSowChecklistItemRequest $request,
        SowReport $report,
        SowChecklistItem $item,
    ): JsonResponse|RedirectResponse {
        $label = $request->string('label')->toString();
        $hasExplainField = (bool) ($request->validated('has_explain_field') ?? false);
        $isRequired = (bool) ($request->validated('is_required') ?? true);

        $updated = $this->updateAction->execute($item, $label, $hasExplainField, $isRequired);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'item' => $updated,
            ]);
        }

        return redirect()->back()->with('success', 'Checklist question updated.');
    }

    public function destroy(Request $request, SowReport $report, SowChecklistItem $item): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $report);

        $this->deleteAction->execute($item);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Checklist question removed.');
    }

    public function reorder(ReorderSowChecklistItemsRequest $request, SowReport $report): JsonResponse|RedirectResponse
    {
        /** @var list<array{id: int, sort_order: int}> $items */
        $items = $request->validated('items');

        $this->reorderAction->execute($report, $items);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Checklist order saved.');
    }
}
