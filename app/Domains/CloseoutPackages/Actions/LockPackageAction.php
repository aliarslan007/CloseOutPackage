<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Actions;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Users\Models\User;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\DB;

final class LockPackageAction
{
    public function execute(CloseoutPackage $package, User $actor): CloseoutPackage
    {
        return DB::transaction(function () use ($package, $actor): CloseoutPackage {
            /** @var CloseoutPackage $locked */
            $locked = CloseoutPackage::query()->lockForUpdate()->findOrFail($package->id);

            if ($locked->status !== PackageStatus::APPROVED) {
                throw new InvalidStateTransitionException('Only fully approved packages can be locked.');
            }

            if ($locked->isLocked()) {
                throw new InvalidStateTransitionException('This package is already locked.');
            }

            $locked->update(['locked_at' => now(), 'locked_by' => $actor->id]);

            activity('approval')
                ->performedOn($locked)
                ->causedBy($actor)
                ->event('package.locked')
                ->log('Package locked');

            return $locked;
        });
    }
}
