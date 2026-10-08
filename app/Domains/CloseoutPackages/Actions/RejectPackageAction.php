<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Actions;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Events\PackageRejected;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Exceptions\RejectionReasonRequiredException;
use Illuminate\Support\Facades\DB;

final class RejectPackageAction
{
    public function execute(CloseoutPackage $package, string $reason): CloseoutPackage
    {
        if ($reason === '') {
            throw new RejectionReasonRequiredException('A rejection reason is required.');
        }

        /** @var PackageStatus $status */
        $status = $package->status;

        if (! in_array($status, [PackageStatus::SUBMITTED, PackageStatus::UNDER_REVIEW], true)) {
            throw new InvalidStateTransitionException('Only submitted packages can be rejected.');
        }

        return DB::transaction(function () use ($package, $reason): CloseoutPackage {
            $package->update(['status' => PackageStatus::REJECTED]);

            PackageRejected::dispatch($package, $reason);

            return $package;
        });
    }
}
