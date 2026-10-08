<?php

declare(strict_types=1);

namespace App\Domains\Crew\Policies;

use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;

class CrewAssignmentPolicy
{
    public function manage(User $user, Project $project): bool
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

        return $user->hasRole(Role::MANAGER->value) && $project->manager_id === $user->id;
    }
}
