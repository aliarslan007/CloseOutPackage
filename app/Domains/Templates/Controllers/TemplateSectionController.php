<?php

declare(strict_types=1);

namespace App\Domains\Templates\Controllers;

use App\Domains\Templates\Actions\UpdateTemplateSectionAction;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplateSection;
use App\Domains\Templates\Requests\UpdateTemplateSectionRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class TemplateSectionController extends Controller
{
    public function __construct(private readonly UpdateTemplateSectionAction $updateSectionAction) {}

    public function update(UpdateTemplateSectionRequest $request, Template $template, TemplateSection $section): RedirectResponse
    {
        abort_unless($section->template_id === $template->id, 404);

        /** @var string $heading */
        $heading = $request->validated('heading');
        $adminNotes = $request->validated('admin_notes');

        $this->updateSectionAction->execute($section, $heading, is_string($adminNotes) ? $adminNotes : null);

        return redirect()->route('admin.templates.show', $template)->with('success', 'Section updated.');
    }
}
