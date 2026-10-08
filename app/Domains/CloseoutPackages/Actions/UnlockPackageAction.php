<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Actions;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Users\Models\User;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\DB;

final class UnlockPackageAction
{
    public function execute(CloseoutPackage $package, User $actor): CloseoutPackage
    {
        return DB::transaction(function () use ($package, $actor): CloseoutPackage {
            /** @var CloseoutPackage $fresh */
            $fresh = CloseoutPackage::query()->lockForUpdate()->findOrFail($package->id);

            if (! $fresh->isLocked()) {
                throw new InvalidStateTransitionException('This package is not locked.');
            }

            $fresh->update(['locked_at' => null, 'locked_by' => null]);

            activity('approval')
                ->performedOn($fresh)
                ->causedBy($actor)
                ->event('package.unlocked')
                ->log('Package unlocked');

            return $fresh;
        });
    }
}
