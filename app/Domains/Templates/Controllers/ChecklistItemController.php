<?php

declare(strict_types=1);

namespace App\Domains\Templates\Controllers;

use App\Domains\Templates\Actions\CreateChecklistItemAction;
use App\Domains\Templates\Actions\DeleteChecklistItemAction;
use App\Domains\Templates\Actions\UpdateChecklistItemAction;
use App\Domains\Templates\Models\ChecklistItem;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Requests\StoreChecklistItemRequest;
use App\Domains\Templates\Requests\UpdateChecklistItemRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class ChecklistItemController extends Controller
{
    public function __construct(
        private readonly CreateChecklistItemAction $createItemAction,
        private readonly UpdateChecklistItemAction $updateItemAction,
        private readonly DeleteChecklistItemAction $deleteItemAction,
    ) {}

    public function store(StoreChecklistItemRequest $request, Template $template): RedirectResponse
    {
        $this->createItemAction->execute($request->toDTO($template->id));

        return redirect()->route('admin.templates.show', $template)->with('success', 'Checklist item added.');
    }

    public function update(UpdateChecklistItemRequest $request, Template $template, ChecklistItem $item): RedirectResponse
    {
        abort_unless($item->template_id === $template->id, 404);

        /** @var string $label */
        $label = $request->validated('label');

        $this->updateItemAction->execute(
            $item,
            $label,
            (bool) $request->validated('has_explain_field', false),
            (bool) $request->validated('allows_na', false),
        );

        return redirect()->route('admin.templates.show', $template)->with('success', 'Checklist item updated.');
    }

    public function destroy(Template $template, ChecklistItem $item): RedirectResponse
    {
        $this->authorize('update', $template);

        abort_unless($item->template_id === $template->id, 404);

        $this->deleteItemAction->execute($item);

        return redirect()->route('admin.templates.show', $template)->with('success', 'Checklist item deleted.');
    }
}
