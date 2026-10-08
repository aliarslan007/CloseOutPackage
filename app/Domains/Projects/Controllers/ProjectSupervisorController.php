<?php

declare(strict_types=1);

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Actions\AssignFieldSupervisorAction;
use App\Domains\Projects\Actions\RemoveFieldSupervisorAction;
use App\Domains\Projects\Actions\SetPrimarySupervisorAction;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Requests\AssignFieldSupervisorRequest;
use App\Domains\Projects\Requests\SetPrimarySupervisorRequest;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class ProjectSupervisorController extends Controller
{
    public function __construct(
        private readonly AssignFieldSupervisorAction $assignFieldSupervisorAction,
        private readonly RemoveFieldSupervisorAction $removeFieldSupervisorAction,
        private readonly SetPrimarySupervisorAction $setPrimarySupervisorAction,
    ) {}

    public function store(AssignFieldSupervisorRequest $request, Project $project): RedirectResponse
    {
        /** @var int $userId */
        $userId = $request->validated('user_id');

        $this->assignFieldSupervisorAction->execute($project, $userId, $this->user());

        return redirect()->route('projects.show', $project)->with('success', 'Field supervisor assigned.');
    }

    public function destroy(Project $project, User $user): RedirectResponse
    {
        $this->authorize('assignUsers', $project);

        $this->removeFieldSupervisorAction->execute($project, $user, $this->user());

        return redirect()->route('projects.show', $project)->with('success', 'Field supervisor removed.');
    }

    public function setPrimary(SetPrimarySupervisorRequest $request, Project $project): RedirectResponse
    {
        /** @var int|null $userId */
        $userId = $request->validated('user_id');

        $this->setPrimarySupervisorAction->execute(
            $project,
            $userId === null ? null : (int) $userId,
            $this->user(),
        );

        return redirect()->back()->with('success', 'Primary field technician updated.');
    }
}
