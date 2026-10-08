<?php

declare(strict_types=1);

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Actions\RecordIssueHistoryAction;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Queries\ProjectQuery;
use App\Domains\Projects\Requests\StoreIssueHistoryRequest;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use App\Shared\Enums\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class IssueHistoryController extends Controller
{
    public function __construct(
        private readonly ProjectQuery $projectQuery,
        private readonly RecordIssueHistoryAction $recordIssueHistoryAction,
    ) {}

    public function index(Project $project): Response
    {
        $this->authorize('view', [IssueHistory::class, $project]);

        $project->load([
            'manager.roles',
            'manager.media',
            'fieldSupervisors.roles',
            'fieldSupervisors.media',
        ]);

        if ($project->manager !== null) {
            $project->manager->setAttribute('profile_photo_url', $project->manager->profilePhotoUrl());
        }

        $project->fieldSupervisors->each(function (User $supervisor): void {
            $supervisor->setAttribute('profile_photo_url', $supervisor->profilePhotoUrl());
        });

        return Inertia::render('Projects/Issues', [
            'project' => $project,
            'issues' => $this->projectQuery->issues($project, $this->user()),
            'canComment' => $this->user()->hasAnyRole([
                Role::ADMIN->value,
                Role::MANAGER->value,
                Role::FIELD_SUPERVISOR->value,
            ]),
            'customers' => $this->projectCustomers($project),
            'canForward' => $this->user()->hasAnyRole([
                Role::ADMIN->value,
                Role::MANAGER->value,
            ]),
            'allowMediaEditWhenResolved' => (bool) config('issues.allow_media_edit_when_resolved'),
        ]);
    }

    public function store(StoreIssueHistoryRequest $request, Project $project): RedirectResponse
    {
        $this->recordIssueHistoryAction->execute($project, $request->toDTO(), $this->user(), $request->attachments());

        return redirect()->route('projects.issues.index', $project)->with('success', 'Issue recorded.');
    }

    public function destroy(Project $project, IssueHistory $issue): RedirectResponse
    {
        $this->authorize('delete', [$issue, $project]);

        $issue->delete();

        return redirect()->route('projects.issues.index', $project)->with('success', 'Issue deleted.');
    }

    /**
     * @return Collection<int, array{id: int, name: string, email: string, profile_photo_url: ?string}>
     */
    private function projectCustomers(Project $project): Collection
    {
        return $project->customerAccesses()
            ->with(['user.media'])
            ->get()
            ->filter(fn ($access): bool => $access->user !== null)
            ->map(fn ($access): array => [
                'id' => $access->user->id,
                'name' => $access->user->name,
                'email' => $access->user->email,
                'profile_photo_url' => $access->user->profilePhotoUrl(),
            ])
            ->unique('id')
            ->values();
    }
}
