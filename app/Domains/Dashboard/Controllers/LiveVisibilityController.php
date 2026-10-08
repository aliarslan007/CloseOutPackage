<?php

declare(strict_types=1);

namespace App\Domains\Dashboard\Controllers;

use App\Domains\Dashboard\Services\LiveProjectVisibilityService;
use App\Domains\Projects\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LiveVisibilityController extends Controller
{
    public function __construct(private readonly LiveProjectVisibilityService $visibilityService) {}

    public function index(): Response
    {
        return Inertia::render('Manager/Visibility', [
            'snapshot' => $this->visibilityService->snapshot($this->user()),
        ]);
    }

    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $issuesPage = max(1, (int) $request->query('issues_page', 1));

        return Inertia::render('Manager/Visibility/Project', [
            'project' => $this->visibilityService->projectDetail($project, $this->user(), $issuesPage),
            'sitePhotoUrl' => $this->visibilityService->sitePhotoUrl($project),
        ]);
    }
}
