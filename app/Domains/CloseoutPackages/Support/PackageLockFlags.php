<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Support;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Gate;

final class PackageLockFlags
{
    /**
     * Attach per-user lock flags to a collection of packages.
     *
     * Packages must have their `project` relationship loaded (at minimum
     * `project:id,manager_id`) before calling this method, otherwise every
     * lock/delete gate check triggers its own project query.
     *
     * @param  iterable<int, CloseoutPackage>  $packages
     */
    public static function attach(iterable $packages, User $user): void
    {
        foreach ($packages as $package) {
            $package->setAttribute('can_open', Gate::forUser($user)->allows('view', $package));
            $package->setAttribute('can_lock', Gate::forUser($user)->allows('lock', $package));
            $package->setAttribute('can_unlock', Gate::forUser($user)->allows('unlock', $package));
        }
    }
}
