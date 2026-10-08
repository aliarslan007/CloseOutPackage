<?php

declare(strict_types=1);

namespace App\Domains\Crew\Actions;

use App\Domains\Crew\Models\CrewAssignment;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Users\Models\User;

final class AssignCrewMemberAction
{
    public function execute(Project $project, int $userId, ?StageType $stage, ?string $roleDescription): CrewAssignment
    {
        $user = User::query()->findOrFail($userId);

        return CrewAssignment::firstOrCreate(
            [
                'project_id' => $project->id,
                'user_id' => $user->id,
                'stage' => $stage?->value,
            ],
            ['role_description' => $roleDescription],
        );
    }
}
