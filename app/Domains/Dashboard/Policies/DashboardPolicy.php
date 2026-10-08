<?php

declare(strict_types=1);

namespace App\Domains\Dashboard\Policies;

use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;

class DashboardPolicy
{
    public function view(User $user): bool
    {
        return $user->hasAnyRole([
            Role::ADMIN->value,
            Role::MANAGER->value,
            Role::FIELD_SUPERVISOR->value,
            Role::CUSTOMER->value,
        ]);
    }

    public function viewActivities(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]);
    }

    public function viewAllActivities(User $user): bool
    {
        return $user->hasRole(Role::ADMIN->value);
    }
}
