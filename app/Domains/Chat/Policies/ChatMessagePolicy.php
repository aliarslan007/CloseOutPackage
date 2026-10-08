<?php

declare(strict_types=1);

namespace App\Domains\Chat\Policies;

use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;

class ChatMessagePolicy
{
    public function view(User $user, Project $project): bool
    {
        return $this->isParticipant($user, $project);
    }

    public function create(User $user, Project $project): bool
    {
        return $this->isParticipant($user, $project);
    }

    private function isParticipant(User $user, Project $project): bool
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

        if ($user->hasRole(Role::MANAGER->value) && $project->manager_id === $user->id) {
            return true;
        }

        return $user->hasRole(Role::FIELD_SUPERVISOR->value)
            && $project->fieldSupervisors()->whereKey($user->id)->exists();
    }
}
