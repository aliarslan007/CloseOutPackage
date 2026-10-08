<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Actions;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use Illuminate\Support\Facades\DB;

final class MarkPackageUnderReviewAction
{
    public function execute(CloseoutPackage $package): CloseoutPackage
    {
        if ($package->status !== PackageStatus::SUBMITTED) {
            return $package;
        }

        return DB::transaction(function () use ($package): CloseoutPackage {
            $package->update(['status' => PackageStatus::UNDER_REVIEW]);

            activity('approval')
                ->performedOn($package)
                ->event('package.under_review')
                ->log('Package review started');

            return $package;
        });
    }
}
