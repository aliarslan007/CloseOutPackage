<?php

declare(strict_types=1);

namespace App\Domains\Templates\Controllers;

use App\Domains\Templates\Actions\UpdateTemplateAdditionalNotesAction;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Requests\UpdateTemplateAdditionalNotesRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class TemplateAdditionalNotesController extends Controller
{
    public function __construct(private readonly UpdateTemplateAdditionalNotesAction $updateAdditionalNotesAction) {}

    public function update(UpdateTemplateAdditionalNotesRequest $request, Template $template): RedirectResponse
    {
        $notes = $request->validated('notes');

        $this->updateAdditionalNotesAction->execute($template, is_string($notes) ? $notes : null);

        return redirect()->back()->with('success', 'Additional site notes saved.');
    }
}
