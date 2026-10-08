<?php

declare(strict_types=1);

namespace App\Domains\Settings\Policies;

use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;

class SettingsPolicy
{
    /**
     * Managers may open the console to add customer engineers in their own
     * regions. The hierarchy payload is region-scoped for them and the
     * mail/quota props are withheld by the controller, so opening the page
     * exposes no admin-only settings.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]);
    }

    /**
     * Writing mail settings or storage quotas stays admin-only.
     */
    public function update(User $user): bool
    {
        return $user->hasRole(Role::ADMIN->value);
    }
}
