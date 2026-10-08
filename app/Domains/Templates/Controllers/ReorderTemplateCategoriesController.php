<?php

declare(strict_types=1);

namespace App\Domains\Templates\Controllers;

use App\Domains\Templates\Actions\ReorderCategoriesAction;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplateSection;
use App\Domains\Templates\Requests\ReorderCategoriesRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class ReorderTemplateCategoriesController extends Controller
{
    public function __construct(private readonly ReorderCategoriesAction $reorderCategoriesAction) {}

    public function __invoke(ReorderCategoriesRequest $request, Template $template, TemplateSection $section): RedirectResponse
    {
        abort_unless($section->template_id === $template->id, 404);

        $this->reorderCategoriesAction->execute($section, $request->toDTO());

        return redirect()->route('admin.templates.show', $template)->with('success', 'Categories reordered.');
    }
}
