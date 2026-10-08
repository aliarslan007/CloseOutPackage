<?php

declare(strict_types=1);

namespace App\Domains\Customers\Policies;

use App\Domains\Customers\Models\Customer;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::ADMIN->value);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->hasRole(Role::ADMIN->value);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->hasRole(Role::ADMIN->value);
    }
}
