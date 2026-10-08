<?php

declare(strict_types=1);

namespace App\Domains\Templates\Controllers;

use App\Domains\Templates\Actions\DuplicateTemplateAction;
use App\Domains\Templates\Models\Template;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class DuplicateTemplateController extends Controller
{
    public function __construct(private readonly DuplicateTemplateAction $duplicateTemplateAction) {}

    public function __invoke(Template $template): RedirectResponse
    {
        $this->authorize('duplicate', $template);

        $this->duplicateTemplateAction->execute($template);

        return redirect()->route('admin.templates.index')->with('success', 'Template duplicated.');
    }
}
