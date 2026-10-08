<?php

declare(strict_types=1);

namespace App\Domains\Projects\Policies;

use App\Domains\CustomerPortal\Services\CustomerPortalScopeService;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;

class ProjectPolicy
{
    public function __construct(private readonly CustomerPortalScopeService $scopeService) {}

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]);
    }

    public function view(User $user, Project $project): bool
    {
        if ($user->hasRole(Role::CUSTOMER->value)) {
            return $this->scopeService->hasProjectAccess($user, $project);
        }

        return $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value])
            || $project->fieldSupervisors()->whereKey($user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::ADMIN->value)
            || ($user->hasRole(Role::MANAGER->value) && $user->markets()->exists());
    }

    public function update(User $user, Project $project): bool
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

        return $user->hasRole(Role::MANAGER->value) && $project->manager_id === $user->id;
    }

    public function delete(User $user, Project $project): bool
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

        return $user->hasRole(Role::MANAGER->value) && $project->manager_id === $user->id;
    }

    public function assignUsers(User $user, Project $project): bool
    {
        return $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]);
    }
}
