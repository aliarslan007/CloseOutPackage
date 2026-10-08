<?php

declare(strict_types=1);

namespace App\Domains\Users\Queries;

use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class UserQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, User>
     */
    public function listForAdmin(array $filters = []): Collection
    {
        $query = User::query()
            ->with(['roles', 'markets', 'media'])
            ->whereDoesntHave('roles', fn (Builder $r): Builder => $r->where('name', Role::CUSTOMER->value));

        $search = $filters['search'] ?? null;
        if (is_string($search) && $search !== '') {
            $query->where(function (Builder $sub) use ($search): void {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $role = $filters['role'] ?? null;
        if (is_string($role) && $role !== '') {
            $query->whereHas('roles', fn (Builder $r) => $r->where('name', $role));
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (($filters['market_filter'] ?? null) === 'with_markets') {
            $query->has('markets');
        }

        if (($filters['market_filter'] ?? null) === 'without_markets') {
            $query->doesntHave('markets');
        }

        return $query->orderBy('name')->get()->each(
            fn (User $user): User => $user->setAttribute('profile_photo_url', $user->profilePhotoUrl())
        );
    }

    public function show(User $user): User
    {
        return $user->load(['roles', 'markets', 'media'])
            ->setAttribute('profile_photo_url', $user->profilePhotoUrl());
    }

    /**
     * Users that can be assigned to a market. Markets scope manager work, so
     * only active managers are assignable.
     *
     * @return Collection<int, User>
     */
    public function assignableForMarket(): Collection
    {
        return User::query()
            ->with('roles')
            ->whereHas('roles', fn ($query) => $query->where('name', Role::MANAGER->value))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    /**
     * Active managers, for the project manager assignment dropdown.
     * When a market id is given, only managers with access to that market are returned.
     *
     * @return Collection<int, User>
     */
    public function managersForProject(?int $marketId = null): Collection
    {
        return $this->byRole(Role::MANAGER->value)
            ->when(
                $marketId !== null,
                fn (Builder $query): Builder => $query->whereHas('markets', fn (Builder $q): Builder => $q->whereKey($marketId)),
            )
            ->get(['id', 'name', 'email']);
    }

    /**
     * Every admin, for change-order decision notifications and the customer
     * fallback contacts shown when a decision link is dead.
     *
     * @return Collection<int, User>
     */
    public function admins(): Collection
    {
        return User::role(Role::ADMIN->value)->with('roles')->orderBy('name')->get(['id', 'name', 'email']);
    }

    /**
     * Active field supervisors, for the optional supervisor assignment dropdown.
     *
     * @return Collection<int, User>
     */
    public function supervisorsForProject(): Collection
    {
        return $this->byRole(Role::FIELD_SUPERVISOR->value)
            ->get(['id', 'name', 'email']);
    }

    /**
     * Roles are always eager-loaded: these collections are serialized into
     * Inertia props and notifications, where the `role_name` append would
     * otherwise lazy-load (fatal outside production, N+1 within it).
     *
     * @return Builder<User>
     */
    private function byRole(string $roleName): Builder
    {
        return User::query()
            ->with('roles')
            ->whereHas('roles', fn ($query) => $query->where('name', $roleName))
            ->where('is_active', true)
            ->orderBy('name');
    }
}
