<?php

declare(strict_types=1);

namespace App\Domains\EmailTemplates\Policies;

use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;

class EmailTemplatePolicy
{
    /**
     * The template library is shared read-only material: managers read it for
     * the send-flow picker, but only admins curate it. Permissions are purely
     * role-based — no ability depends on the specific template instance.
     */
    public function viewAny(User $user): bool
    {
        return $this->canReadTemplates($user);
    }

    public function view(User $user): bool
    {
        return $this->canReadTemplates($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageTemplates($user);
    }

    public function update(User $user): bool
    {
        return $this->canManageTemplates($user);
    }

    public function delete(User $user): bool
    {
        return $this->canManageTemplates($user);
    }

    public function setDefault(User $user): bool
    {
        return $this->canManageTemplates($user);
    }

    public function duplicate(User $user): bool
    {
        return $this->canManageTemplates($user);
    }

    private function canReadTemplates(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]);
    }

    private function canManageTemplates(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]);
    }
}
