<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Actions;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Events\PackageApproved;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Users\Models\User;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\DB;

final class ApprovePackageAction
{
    public function execute(CloseoutPackage $package, User $manager): CloseoutPackage
    {
        /** @var PackageStatus $status */
        $status = $package->status;

        if (! in_array($status, [PackageStatus::SUBMITTED, PackageStatus::UNDER_REVIEW], true)) {
            throw new InvalidStateTransitionException('Only submitted packages can be approved.');
        }

        return DB::transaction(function () use ($package, $manager): CloseoutPackage {
            $now = now();

            $package->update([
                'status' => PackageStatus::APPROVED,
                'approved_by' => $manager->id,
                'approved_at' => $now,
            ]);

            // Stamp the project's close-out milestone with the approval date so
            // "how long did this take" is measurable without a manual entry.
            $package->project?->update(['closeout_package_date' => $now]);

            if ($package->siteInfo) {
                $deadline = $package->project?->completed_date?->toDateString() ?? $package->siteInfo->complete_date?->toDateString();
                if ($deadline) {
                    $package->siteInfo->update(['complete_date' => $deadline]);
                }
            }

            PackageApproved::dispatch($package);

            return $package;
        });
    }
}
