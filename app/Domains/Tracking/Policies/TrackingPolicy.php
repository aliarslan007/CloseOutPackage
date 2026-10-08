<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Policies;

use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;

class TrackingPolicy
{
    /** Manager + admin may view the tracking overview. */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]);
    }

    /** Manager + admin only — field techs only send pings, never view the map. */
    public function viewTracking(User $user, Project $project): bool
    {
        if (! $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value])) {
            return false;
        }

        // Managers are scoped to their own projects.
        if ($user->hasRole(Role::MANAGER->value) && ! $user->hasRole(Role::ADMIN->value)) {
            return $project->manager_id === $user->id;
        }

        return true;
    }

    /** Field techs may ping projects they are assigned to. */
    public function ping(User $user, Project $project): bool
    {
        return $project->fieldSupervisors()->whereKey($user->id)->exists();
    }
}
