<?php

declare(strict_types=1);

namespace App\Domains\Projects\Controllers;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Queries\CustomerHierarchyQuery;
use App\Domains\Markets\Queries\MarketQuery;
use App\Domains\Projects\Actions\CreateProjectAction;
use App\Domains\Projects\Actions\DeleteProjectAction;
use App\Domains\Projects\Actions\SetPrimarySupervisorAction;
use App\Domains\Projects\Actions\SyncFieldSupervisorsAction;
use App\Domains\Projects\Actions\UpdateProjectAction;
use App\Domains\Projects\Actions\UploadSitePhotoAction;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Queries\ProjectQuery;
use App\Domains\Projects\Requests\StoreProjectRequest;
use App\Domains\Projects\Requests\UpdateProjectRequest;
use App\Domains\Users\Models\User;
use App\Domains\Users\Queries\UserQuery;
use App\Http\Controllers\Controller;
use App\Shared\Enums\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectQuery $projectQuery,
        private readonly MarketQuery $marketQuery,
        private readonly UserQuery $userQuery,
        private readonly CreateProjectAction $createProjectAction,
        private readonly UpdateProjectAction $updateProjectAction,
        private readonly DeleteProjectAction $deleteProjectAction,
        private readonly UploadSitePhotoAction $uploadSitePhotoAction,
        private readonly SyncFieldSupervisorsAction $syncFieldSupervisorsAction,
        private readonly SetPrimarySupervisorAction $setPrimarySupervisorAction,
        private readonly CustomerHierarchyQuery $customerHierarchyQuery,
    ) {}

    public function index(Request $request): Response
    {
        /** @var array{search?: ?string, status?: ?string, market_id?: ?int, package_status?: ?string, assignment?: ?string, sort?: ?string, direction?: ?string} $filters */
        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'market_id' => $request->integer('market_id') ?: null,
            'package_status' => $request->string('package_status')->toString() ?: null,
            'assignment' => $request->string('assignment')->toString() ?: null,
            'sort' => $request->string('sort')->toString() ?: null,
            'direction' => $request->string('direction')->toString() ?: null,
        ];

        $managers = $this->userQuery->managersForProject();
        $supervisors = $this->userQuery->supervisorsForProject();

        return Inertia::render('Projects/Index', [
            'projects' => $this->projectQuery->forIndex($this->user(), $filters),
            'markets' => $this->projectQuery->marketOptions($this->user()),
            'managers' => $managers,
            'supervisors' => $supervisors,
            'filters' => $filters,
            // ProjectPolicy::create, computed server-side. It depends on market
            // *assignment*, not on the filter's market list, so it cannot be
            // derived from the `markets` prop in the page.
            'can_create_project' => Gate::allows('create', Project::class),
        ]);
    }

    public function create(): Response
    {
        $managers = $this->userQuery->managersForProject();
        $supervisors = $this->userQuery->supervisorsForProject();

        return Inertia::render('Projects/Create', [
            'markets' => $this->marketQuery->dropdownFor($this->user()),
            'managers' => $managers,
            'supervisors' => $supervisors,
            ...$this->customerHierarchyOptions(),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = $this->createProjectAction->execute($request->toDTO(), $this->user());

        $this->syncSupervisors($request, $project);
        $this->uploadSitePhoto($request, $project);

        return redirect()->route('projects.index')->with('success', 'Project created.');
    }

    public function show(Project $project): Response
    {
        $this->authorize('view', $project);

        $projectDetails = $this->projectQuery->show($project, $this->user());

        // ponytail: hide field tech email from customer portal – customer must not see PII, manager email stays visible
        if ($this->user()->hasRole(Role::CUSTOMER->value)) {
            $projectDetails->fieldSupervisors->each(fn (User $u) => $u->makeHidden(['email']));
        }

        return Inertia::render('Projects/Show', [
            'project' => $projectDetails,
            'sitePhotoUrl' => $this->sitePhotoUrl($projectDetails),
            'projectDocuments' => $this->projectQuery->projectDocuments($projectDetails),
            // Mirrors the create() policy so the package card only offers creation
            // to users the backend would actually allow (assigned roles only).
            'can_create_package' => Gate::allows('create', [CloseoutPackage::class, $projectDetails]),
        ]);
    }

    public function edit(Project $project): Response
    {
        $this->authorize('update', $project);

        $managers = $this->userQuery->managersForProject($project->market_id);
        $supervisors = $this->userQuery->supervisorsForProject();

        return Inertia::render('Projects/Edit', [
            'project' => $project->load(['fieldSupervisors.roles', 'fieldSupervisors.media']),
            'sitePhotoUrl' => $this->sitePhotoUrl($project),
            'markets' => $this->marketQuery->dropdownFor($this->user()),
            'managers' => $managers,
            'supervisors' => $supervisors,
            ...$this->customerHierarchyOptions(),
        ]);
    }

    /**
     * Cascading selector data for the project form: customers plus their
     * engineers grouped by customer and region (market).
     *
     * @return array{customers: Collection<int, Customer>, engineers: array<int, array<int, list<array{id: int, name: string, email: string, phone: string|null, title: string|null, is_default: bool}>>>}
     */
    private function customerHierarchyOptions(): array
    {
        return [
            'customers' => $this->customerHierarchyQuery->activeCustomers(),
            'engineers' => $this->customerHierarchyQuery->engineersByCustomerAndMarket(),
        ];
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->updateProjectAction->execute($project, $request->toDTO(), $this->user());

        $this->syncSupervisors($request, $project);
        $this->uploadSitePhoto($request, $project);

        return redirect()->route('projects.show', $project)->with('success', 'Project updated.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $this->deleteProjectAction->execute($project);

        return redirect()->route('projects.index')->with('success', 'Project deleted.');
    }

    private function syncSupervisors(Request $request, Project $project): void
    {
        if ($request->has('supervisor_ids')) {
            $supervisorIds = $request->input('supervisor_ids', []);

            if (is_array($supervisorIds)) {
                /** @var array<int, int|string> $supervisorIds */
                $this->syncFieldSupervisorsAction->execute($project, $supervisorIds, $this->user());
            }
        }

        $this->syncPrimarySupervisor($request, $project);
    }

    /**
     * The form sends supervisor_ids and primary_supervisor_id together, so the
     * primary is resolved after the assignment sync. A primary that is not (or
     * is no longer) an assigned supervisor clears the field rather than
     * leaving a dangling reference.
     */
    private function syncPrimarySupervisor(Request $request, Project $project): void
    {
        if (! $request->has('primary_supervisor_id')) {
            return;
        }

        $rawPrimaryId = $request->input('primary_supervisor_id');
        $primaryId = is_numeric($rawPrimaryId) ? (int) $rawPrimaryId : null;

        $isAssigned = $primaryId !== null
            && $project->fieldSupervisors()->whereKey($primaryId)->exists();

        $this->setPrimarySupervisorAction->execute($project, $isAssigned ? $primaryId : null, $this->user());
    }

    private function uploadSitePhoto(StoreProjectRequest|UpdateProjectRequest $request, Project $project): void
    {
        $file = $request->file('site_photo');

        if ($file !== null) {
            $this->uploadSitePhotoAction->execute($project, $file);
        }
    }

    private function sitePhotoUrl(Project $project): ?string
    {
        $media = $this->projectQuery->sitePhoto($project);

        if ($media === null) {
            return null;
        }

        // Cache-bust with the media id so a replaced photo gets a fresh URL.
        return route('projects.site-photo.show', $project).'?v='.$media->id;
    }
}
