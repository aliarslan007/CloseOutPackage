<?php

declare(strict_types=1);

namespace App\Domains\CustomerPortal\Queries;

use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class CustomerAccessQuery
{
    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function forUser(User $user): LengthAwarePaginator
    {
        $isAdmin = $user->hasRole(Role::ADMIN->value);

        return User::query()
            ->role(Role::CUSTOMER->value)
            ->with([
                'customerAccesses' => function ($query) use ($user, $isAdmin): void {
                    $query->whereHas('project', function (Builder $pq) use ($user, $isAdmin): void {
                        if (! $isAdmin) {
                            $pq->where(function (Builder $sub) use ($user): void {
                                $sub->where('manager_id', $user->id)
                                    ->orWhereHas('fieldSupervisors', fn (Builder $fs): Builder => $fs->whereKey($user->id));
                            });
                        }
                    });
                },
                'customerAccesses.project',
                'customerAccesses.grantedBy',
            ])
            ->latest()
            ->paginate(20);
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function forAdmin(): LengthAwarePaginator
    {
        return User::query()
            ->role(Role::CUSTOMER->value)
            ->with([
                'customerAccesses' => fn ($query) => $query->whereHas('project'),
                'customerAccesses.project',
                'customerAccesses.grantedBy',
            ])
            ->latest()
            ->paginate(20);
    }
}
