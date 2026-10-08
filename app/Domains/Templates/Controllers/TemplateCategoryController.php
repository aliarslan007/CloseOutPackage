<?php

declare(strict_types=1);

namespace App\Domains\Templates\Controllers;

use App\Domains\Templates\Actions\CreateTemplatePictureCategoryAction;
use App\Domains\Templates\Actions\DeleteTemplatePictureCategoryAction;
use App\Domains\Templates\Actions\PullCategoryPresetsAction;
use App\Domains\Templates\Actions\UpdateTemplatePictureCategoryAction;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplatePictureCategory;
use App\Domains\Templates\Models\TemplateSection;
use App\Domains\Templates\Requests\PullCategoriesFromLibraryRequest;
use App\Domains\Templates\Requests\StoreTemplatePictureCategoryRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class TemplateCategoryController extends Controller
{
    public function __construct(
        private readonly CreateTemplatePictureCategoryAction $createCategoryAction,
        private readonly UpdateTemplatePictureCategoryAction $updateCategoryAction,
        private readonly DeleteTemplatePictureCategoryAction $deleteCategoryAction,
        private readonly PullCategoryPresetsAction $pullPresetsAction,
    ) {}

    public function store(StoreTemplatePictureCategoryRequest $request, Template $template, TemplateSection $section): RedirectResponse
    {
        abort_unless($section->template_id === $template->id, 404);

        $this->createCategoryAction->execute($request->toDTO($section->id));

        return redirect()->route('admin.templates.show', $template)->with('success', 'Picture category added.');
    }

    public function pullFromLibrary(
        PullCategoriesFromLibraryRequest $request,
        Template $template,
        TemplateSection $section,
    ): RedirectResponse {
        abort_unless($section->template_id === $template->id, 404);

        $result = $this->pullPresetsAction->execute($section->id, $request->presetIds());

        if ($result['added'] === 0) {
            return redirect()
                ->route('admin.templates.show', $template)
                ->with('error', 'Those photo groups are already in this stage.');
        }

        $message = $result['skipped'] > 0
            ? "Added {$result['added']} photo group(s), {$result['skipped']} already in this stage."
            : "Added {$result['added']} photo group(s).";

        return redirect()->route('admin.templates.show', $template)->with('success', $message);
    }

    public function update(
        StoreTemplatePictureCategoryRequest $request,
        Template $template,
        TemplateSection $section,
        TemplatePictureCategory $category,
    ): RedirectResponse {
        abort_unless($section->template_id === $template->id, 404);
        abort_unless($category->template_section_id === $section->id, 404);

        /** @var string $name */
        $name = $request->validated('name');
        $description = $request->validated('description');
        /** @var int $defaultPictureCount */
        $defaultPictureCount = $request->validated('default_picture_count');

        $this->updateCategoryAction->execute(
            $category,
            $name,
            is_string($description) ? $description : null,
            $defaultPictureCount,
            (bool) $request->validated('is_required', false),
        );

        return redirect()->route('admin.templates.show', $template)->with('success', 'Picture category updated.');
    }

    public function destroy(Template $template, TemplateSection $section, TemplatePictureCategory $category): RedirectResponse
    {
        $this->authorize('update', $template);

        abort_unless($section->template_id === $template->id, 404);
        abort_unless($category->template_section_id === $section->id, 404);

        $this->deleteCategoryAction->execute($category);

        return redirect()->route('admin.templates.show', $template)->with('success', 'Picture category deleted.');
    }
}
