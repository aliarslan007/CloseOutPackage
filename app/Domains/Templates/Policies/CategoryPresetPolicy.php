<?php

declare(strict_types=1);

namespace App\Domains\Templates\Policies;

use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;

class CategoryPresetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::ADMIN->value);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::ADMIN->value);
    }

    public function update(User $user): bool
    {
        return $user->hasRole(Role::ADMIN->value);
    }

    public function delete(User $user): bool
    {
        return $user->hasRole(Role::ADMIN->value);
    }
}
