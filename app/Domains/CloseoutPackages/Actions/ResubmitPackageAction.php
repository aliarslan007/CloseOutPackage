<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Actions;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Events\PackageResubmitted;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Photos\Models\Photo;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\DB;

final class ResubmitPackageAction
{
    private const PENDING_REVIEW_STATUSES = [
        PhotoStatus::FLAGGED,
        PhotoStatus::REJECTED,
        PhotoStatus::REPLACEMENT_REQUESTED,
    ];

    public function execute(CloseoutPackage $package): CloseoutPackage
    {
        /** @var PackageStatus $status */
        $status = $package->status;

        if ($status !== PackageStatus::REJECTED) {
            throw new InvalidStateTransitionException('Only rejected packages can be resubmitted.');
        }

        $hasPendingReplacements = Photo::query()
            ->where('closeout_package_id', $package->id)
            ->whereNull('replaced_at')
            ->whereIn('status', array_map(fn (PhotoStatus $s): string => $s->value, self::PENDING_REVIEW_STATUSES))
            ->exists();

        if ($hasPendingReplacements) {
            throw new InvalidStateTransitionException('Replace all flagged photos before resubmitting the package.');
        }

        return DB::transaction(function () use ($package): CloseoutPackage {
            $package->update([
                'status' => PackageStatus::SUBMITTED,
                'submitted_at' => now(),
            ]);

            PackageResubmitted::dispatch($package);

            return $package;
        });
    }
}
