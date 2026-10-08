<?php

declare(strict_types=1);

namespace App\Domains\Templates\Policies;

use App\Domains\Markets\Models\Market;
use App\Domains\Templates\Models\Template;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;

class TemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]);
    }

    public function view(User $user, Template $template): bool
    {
        if ($user->hasRole(Role::ADMIN->value) || $user->hasRole(Role::CUSTOMER->value)) {
            return true;
        }

        if (! $user->hasAnyRole([Role::MANAGER->value, Role::FIELD_SUPERVISOR->value])) {
            return false;
        }

        $market = $template->market_id === null ? null : Market::find($template->market_id);

        return $template->is_active || $market === null || $market->userHasAccess($user);
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

    public function duplicate(User $user): bool
    {
        return $user->hasRole(Role::ADMIN->value);
    }
}
