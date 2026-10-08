<?php

declare(strict_types=1);

namespace App\Domains\Crew\Controllers;

use App\Domains\Crew\Actions\AssignCrewMemberAction;
use App\Domains\Crew\Actions\RemoveCrewMemberAction;
use App\Domains\Crew\Models\CrewAssignment;
use App\Domains\Crew\Requests\AssignCrewMemberRequest;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class CrewController extends Controller
{
    public function __construct(
        private readonly AssignCrewMemberAction $assignCrewMemberAction,
        private readonly RemoveCrewMemberAction $removeCrewMemberAction,
    ) {}

    public function store(AssignCrewMemberRequest $request, Project $project): RedirectResponse
    {
        /** @var int $userId */
        $userId = $request->validated('user_id');
        $stage = $request->validated('stage');
        $roleDescription = $request->validated('role_description');

        $this->assignCrewMemberAction->execute(
            $project,
            $userId,
            is_string($stage) ? StageType::from($stage) : null,
            is_string($roleDescription) ? $roleDescription : null,
        );

        return redirect()->back()->with('success', 'Crew member assigned.');
    }

    public function destroy(Project $project, User $user): RedirectResponse
    {
        $this->authorize('manage', [CrewAssignment::class, $project]);

        $this->removeCrewMemberAction->execute($project, $user);

        return redirect()->back()->with('success', 'Crew member removed.');
    }
}
