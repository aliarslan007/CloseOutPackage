<?php

declare(strict_types=1);

namespace App\Domains\Customers\Policies;

use App\Domains\Customers\Models\CustomerEngineer;
use App\Domains\Markets\Models\Market;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;

/**
 * Admins manage engineers everywhere; managers only inside the regions
 * (markets) granted to them through market access.
 */
class CustomerEngineerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]);
    }

    public function manageRegion(User $user, Market $market): bool
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

        return $user->hasRole(Role::MANAGER->value)
            && $user->markets()->whereKey($market->id)->exists();
    }

    public function create(User $user, ?Market $market = null): bool
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

        return $market !== null && $this->manageRegion($user, $market);
    }

    public function update(User $user, CustomerEngineer $engineer): bool
    {
        // Admins manage engineers everywhere — including engineers whose
        // region was soft-deleted (their market relation resolves to null
        // and must not lock the record for administrators).
        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

        $market = $engineer->market;

        return $market !== null && $this->manageRegion($user, $market);
    }

    public function delete(User $user, CustomerEngineer $engineer): bool
    {
        return $this->update($user, $engineer);
    }
}
