<?php

declare(strict_types=1);

namespace App\Domains\Markets\Policies;

use App\Domains\Markets\Models\Market;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;

class MarketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::ADMIN->value);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::ADMIN->value);
    }

    public function update(User $user, Market $market): bool
    {
        return $user->hasRole(Role::ADMIN->value);
    }

    public function delete(User $user, Market $market): bool
    {
        return $user->hasRole(Role::ADMIN->value);
    }

    public function grantAccess(User $user, Market $market): bool
    {
        return $user->hasRole(Role::ADMIN->value);
    }

    public function revokeAccess(User $user, Market $market): bool
    {
        return $user->hasRole(Role::ADMIN->value);
    }
}
