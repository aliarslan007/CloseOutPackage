<?php

declare(strict_types=1);

namespace App\Domains\Templates\Controllers;

use App\Domains\Templates\Actions\CreateCategoryPresetAction;
use App\Domains\Templates\Actions\DeleteCategoryPresetAction;
use App\Domains\Templates\Actions\ToggleCategoryPresetActiveAction;
use App\Domains\Templates\Actions\UpdateCategoryPresetAction;
use App\Domains\Templates\Models\CategoryPreset;
use App\Domains\Templates\Queries\CategoryPresetQuery;
use App\Domains\Templates\Requests\StoreCategoryPresetRequest;
use App\Domains\Templates\Requests\UpdateCategoryPresetRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryPresetController extends Controller
{
    public function __construct(
        private readonly CategoryPresetQuery $categoryPresetQuery,
        private readonly CreateCategoryPresetAction $createAction,
        private readonly UpdateCategoryPresetAction $updateAction,
        private readonly DeleteCategoryPresetAction $deleteAction,
        private readonly ToggleCategoryPresetActiveAction $toggleActiveAction,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CategoryPreset::class);

        /** @var array<string, mixed> $filters */
        $filters = $request->only(['search', 'status', 'sort']);

        return Inertia::render('Admin/Templates/CategoryPresets', [
            'presets' => $this->categoryPresetQuery->listForAdmin($filters),
            'filters' => $filters,
        ]);
    }

    public function store(StoreCategoryPresetRequest $request): RedirectResponse
    {
        $this->createAction->execute($request->toDTO());

        return redirect()
            ->route('admin.templates.category-presets.index')
            ->with('success', 'Subcategory created.');
    }

    public function update(UpdateCategoryPresetRequest $request, CategoryPreset $category_preset): RedirectResponse
    {
        $this->updateAction->execute($category_preset, $request->toDTO());

        return redirect()
            ->route('admin.templates.category-presets.index')
            ->with('success', 'Subcategory updated.');
    }

    public function destroy(CategoryPreset $category_preset): RedirectResponse
    {
        $this->authorize('delete', $category_preset);

        $this->deleteAction->execute($category_preset);

        return redirect()
            ->route('admin.templates.category-presets.index')
            ->with('success', 'Subcategory deleted.');
    }

    public function toggleActive(CategoryPreset $category_preset): RedirectResponse
    {
        $this->authorize('update', $category_preset);

        $preset = $this->toggleActiveAction->execute($category_preset);

        return redirect()
            ->route('admin.templates.category-presets.index')
            ->with('success', $preset->is_active ? 'Subcategory activated.' : 'Subcategory deactivated.');
    }
}
