<?php

declare(strict_types=1);

namespace App\Domains\Users\Policies;

use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;

class UserManagementPolicy
{
    public function manage(User $actor): bool
    {
        return $actor->hasRole(Role::ADMIN->value);
    }

    public function delete(User $actor, User $user): bool
    {
        return $this->manage($actor) && ! $actor->is($user);
    }

    public function changePassword(User $actor, User $user): bool
    {
        return $this->manage($actor) && ! $actor->is($user);
    }

    public function toggleActive(User $actor, User $user): bool
    {
        return $this->manage($actor) && ! $actor->is($user);
    }
}
