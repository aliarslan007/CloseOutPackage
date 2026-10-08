<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Policies;

use App\Domains\ChangeOrders\Enums\ChangeOrderStatus;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\CustomerPortal\Services\CustomerPortalScopeService;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;

class ChangeOrderPolicy
{
    public function __construct(
        private readonly CustomerPortalScopeService $scopeService,
    ) {}

    /** Admin + manager + customer (scoped to accessible project) — field supervisors have no access. */
    public function viewAny(User $user, Project $project): bool
    {
        if ($user->hasRole(Role::CUSTOMER->value)) {
            return $this->scopeService->hasProjectAccess($user, $project);
        }

        return $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]);
    }

    public function view(User $user, ChangeOrder $changeOrder): bool
    {
        if ($user->hasRole(Role::CUSTOMER->value)) {
            return $changeOrder->project !== null
                && $this->scopeService->hasProjectAccess($user, $changeOrder->project)
                && $changeOrder->status !== ChangeOrderStatus::DRAFT;
        }

        return $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]);
    }

    public function create(User $user, Project $project): bool
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

        // Managers may only raise change orders for the projects assigned to
        // them — market membership alone is not enough. Keeps the project picker
        // and direct create URLs consistent.
        return $user->hasRole(Role::MANAGER->value)
            && $project->manager_id !== null
            && $project->manager_id === $user->id;
    }

    public function update(User $user, ChangeOrder $changeOrder): bool
    {
        if (! $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value])) {
            return false;
        }

        return $changeOrder->status->isEditable();
    }

    /** Staff-only display ordering; mirrors viewAny so anyone who can read the
     *  list can arrange it, but customers never reorder. */
    public function reorder(User $user, Project $project): bool
    {
        return $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]);
    }

    /** The all-projects list has its own sequence, with no project to scope to. */
    public function reorderGlobally(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]);
    }

    public function delete(User $user, ChangeOrder $changeOrder): bool
    {
        return $this->isDraftManageable($user, $changeOrder);
    }

    public function send(User $user, ChangeOrder $changeOrder): bool
    {
        if (! $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value])) {
            return false;
        }

        return ! $changeOrder->status->isDecided();
    }

    /** Customer decides via portal project access or valid email token (checked separately). */
    public function decideAsCustomer(User $user, ChangeOrder $changeOrder): bool
    {
        if (! $user->hasRole(Role::CUSTOMER->value)) {
            return false;
        }

        $hasAccess = $changeOrder->project !== null
            && $this->scopeService->hasProjectAccess($user, $changeOrder->project);

        return $hasAccess
            && ! $changeOrder->status->isDecided()
            && $changeOrder->status !== ChangeOrderStatus::DRAFT;
    }

    private function isDraftManageable(User $user, ChangeOrder $changeOrder): bool
    {
        if (! $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value])) {
            return false;
        }

        return $changeOrder->status === ChangeOrderStatus::DRAFT;
    }
}
