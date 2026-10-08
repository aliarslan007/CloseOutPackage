<?php

declare(strict_types=1);

namespace App\Domains\Crew\Actions;

use App\Domains\Crew\Models\CrewAssignment;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;

final class RemoveCrewMemberAction
{
    public function execute(Project $project, User $user): void
    {
        CrewAssignment::query()
            ->where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->delete();
    }
}
