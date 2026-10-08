<?php

declare(strict_types=1);

namespace App\Domains\Templates\Controllers;

use App\Domains\Templates\Actions\ReorderChecklistItemsAction;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Requests\ReorderChecklistItemsRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class ReorderChecklistItemsController extends Controller
{
    public function __construct(private readonly ReorderChecklistItemsAction $reorderItemsAction) {}

    public function __invoke(ReorderChecklistItemsRequest $request, Template $template): RedirectResponse
    {
        $this->reorderItemsAction->execute($template, $request->toDTO());

        return redirect()->route('admin.templates.show', $template)->with('success', 'Checklist items reordered.');
    }
}
