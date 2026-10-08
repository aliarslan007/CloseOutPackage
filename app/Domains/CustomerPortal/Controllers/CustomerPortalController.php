<?php

declare(strict_types=1);

namespace App\Domains\CustomerPortal\Controllers;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CustomerPortal\Queries\CustomerPortalQuery;
use App\Domains\CustomerPortal\Services\CustomerPortalScopeService;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Presenters\ProjectDocumentPresenter;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class CustomerPortalController extends Controller
{
    public function __construct(
        private readonly CustomerPortalScopeService $scopeService,
        private readonly CustomerPortalQuery $portalQuery,
        private readonly ProjectDocumentPresenter $projectDocumentPresenter,
    ) {}

    public function index(): Response
    {
        $projects = $this->scopeService->accessibleProjects($this->user())
            ->with(['market', 'manager.roles', 'manager.media', 'latestPackage'])
            ->withCount(['packages as total_packages_count'])
            ->withCount(['packages as approved_packages_count' => fn (Builder $q) => $q->whereIn('status', [
                PackageStatus::APPROVED->value,
                PackageStatus::CUSTOMER_REVIEW->value,
                PackageStatus::FEEDBACK_RECEIVED->value,
                PackageStatus::REVISION_IN_PROGRESS->value,
            ])])
            ->withCount(['packages as rework_packages_count' => function (Builder $q): void {
                $q->where(function (Builder $inner): void {
                    $inner->where('status', PackageStatus::REJECTED->value)
                        ->orWhereHas('photos', fn (Builder $photos): Builder => $photos
                            ->whereNull('replaced_at')
                            ->whereIn('status', [
                                PhotoStatus::FLAGGED->value,
                                PhotoStatus::REPLACEMENT_REQUESTED->value,
                            ]));
                });
            }])
            ->latest()
            ->get();

        return Inertia::render('Customer/Portal/Index', [
            'projects' => $projects,
        ]);
    }

    public function show(Project $project): Response
    {
        $this->scopeService->ensureAccessible($this->user(), $project);

        $projectDetails = $this->portalQuery->projectDetails($project, $this->user());

        return Inertia::render('Customer/Portal/Projects/Show', [
            'project' => $projectDetails,
            'projectDocuments' => $this->projectDocumentPresenter->present($projectDetails),
        ]);
    }
}
