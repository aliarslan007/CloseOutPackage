<?php

declare(strict_types=1);

namespace App\Domains\Templates\Controllers;

use App\Domains\Markets\Queries\MarketQuery;
use App\Domains\Templates\Actions\CreateTemplateAction;
use App\Domains\Templates\Actions\DeleteTemplateAction;
use App\Domains\Templates\Actions\UpdateTemplateAction;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Queries\CategoryPresetQuery;
use App\Domains\Templates\Queries\TemplateQuery;
use App\Domains\Templates\Requests\StoreTemplateRequest;
use App\Domains\Templates\Requests\UpdateTemplateRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    public function __construct(
        private readonly TemplateQuery $templateQuery,
        private readonly CategoryPresetQuery $categoryPresetQuery,
        private readonly MarketQuery $marketQuery,
        private readonly CreateTemplateAction $createTemplateAction,
        private readonly UpdateTemplateAction $updateTemplateAction,
        private readonly DeleteTemplateAction $deleteTemplateAction,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Templates/Index', $this->templateQuery->groupedByMarket());
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Templates/Create', [
            'markets' => $this->marketQuery->dropdownFor($this->user()),
        ]);
    }

    public function store(StoreTemplateRequest $request): RedirectResponse
    {
        $this->createTemplateAction->execute($request->toDTO(), $this->user());

        return redirect()->route('admin.templates.index')->with('success', 'Template created.');
    }

    public function show(Template $template): Response
    {
        $this->authorize('view', $template);

        return Inertia::render('Admin/Templates/Show', [
            'template' => $this->templateQuery->show($template),
            'libraryPresets' => $this->categoryPresetQuery->listActive(),
        ]);
    }

    public function edit(Template $template): Response
    {
        $this->authorize('update', $template);

        return Inertia::render('Admin/Templates/Edit', [
            'template' => $this->templateQuery->show($template),
            'markets' => $this->marketQuery->dropdownFor($this->user()),
        ]);
    }

    public function update(UpdateTemplateRequest $request, Template $template): RedirectResponse
    {
        $this->updateTemplateAction->execute($template, $request->toDTO());

        return redirect()->route('admin.templates.index')->with('success', 'Template updated.');
    }

    public function destroy(Template $template): RedirectResponse
    {
        $this->authorize('delete', $template);

        $this->deleteTemplateAction->execute($template);

        return redirect()->route('admin.templates.index')->with('success', 'Template deleted.');
    }
}
