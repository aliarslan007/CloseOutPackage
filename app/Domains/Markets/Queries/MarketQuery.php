<?php

declare(strict_types=1);

namespace App\Domains\Markets\Queries;

use App\Domains\Markets\Models\Market;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

final class MarketQuery
{
    private const CACHE_TAG = 'markets';

    private const CACHE_TTL_SECONDS = 300;

    public static function flushCache(): void
    {
        if (Cache::supportsTags()) {
            Cache::tags([self::CACHE_TAG])->flush();
        }
    }

    /**
     * Admin market management list.
     *
     * Deliberately NOT scoped to active: an admin has to see the markets they
     * deactivated in order to reactivate them.
     *
     * @return Collection<int, Market>
     */
    public function listForAdmin(): Collection
    {
        $callback = fn (): Collection => Market::query()
            ->with(['users.roles'])
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return $this->remember('admin.list', $callback);
    }

    /**
     * Selectable markets for create/edit forms (templates, projects).
     *
     * Inactive markets are excluded: a deactivated region must not offer itself
     * as a target for new work.
     *
     * @return Collection<int, Market>
     */
    public function dropdownFor(User $user): Collection
    {
        $callback = fn (): Collection => Market::query()
            ->active()
            ->orderBy('name')
            ->when(
                ! $user->hasRole(Role::ADMIN->value),
                fn ($query) => $query->whereIn('id', $user->markets()->pluck('markets.id')),
            )
            ->get();

        return $this->remember("user.{$user->id}.dropdown", $callback);
    }

    /**
     * Active markets ordered by name (id+name only) — for the user-edit market picker.
     *
     * @return Collection<int, Market>
     */
    public function listActive(): Collection
    {
        return Market::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @param  Closure(): Collection<int, Market>  $callback
     * @return Collection<int, Market>
     */
    private function remember(string $key, Closure $callback): Collection
    {
        if (! Cache::supportsTags()) {
            return $callback();
        }

        return Cache::tags([self::CACHE_TAG])->remember($key, self::CACHE_TTL_SECONDS, $callback);
    }
}
