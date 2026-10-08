<?php

declare(strict_types=1);

namespace App\Domains\Approval\Actions;

use App\Domains\Approval\DTOs\FinalPackageApprovalDTO;
use App\Domains\Approval\Services\ApprovalReadinessService;
use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Events\PackageApproved;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use App\Domains\Users\Models\User;
use App\Shared\Exceptions\PackageNotApprovedException;
use Illuminate\Support\Facades\DB;

final class ApprovePackageFinalAction
{
    public function execute(FinalPackageApprovalDTO $dto, User $manager): CloseoutPackage
    {
        $package = CloseoutPackage::query()->findOrFail($dto->closeoutPackageId);

        $report = app(ApprovalReadinessService::class)->check($package);

        if (! $report->isReady) {
            throw new PackageNotApprovedException('The package is not ready for final approval.');
        }

        return DB::transaction(function () use ($package, $manager, $dto): CloseoutPackage {
            $now = now();

            $package->update([
                'status' => PackageStatus::APPROVED,
                'approved_by' => $manager->id,
                'approved_at' => $now,
            ]);

            $openRound = $package->latestActiveFeedbackRound();
            if ($openRound !== null) {
                $openRound->notes()->whereNull('resolved_at')->update([
                    'resolved_at' => $now,
                    'resolved_by' => $manager->id,
                ]);
                $openRound->update([
                    'status' => FeedbackRoundStatus::ACCEPTED,
                    'accepted_at' => $now,
                    'accepted_by' => $manager->id,
                    'resolved_at' => $openRound->resolved_at ?? $now,
                    'resolved_by' => $openRound->resolved_by ?? $manager->id,
                ]);
            }

            if ($dto->siteDetails !== []) {
                $package->siteDetails()->createMany(
                    array_map(
                        fn (array $entry, int $index): array => [
                            ...$entry,
                            'sort_order' => $index,
                        ],
                        $dto->siteDetails,
                        array_keys($dto->siteDetails),
                    ),
                );
            }

            PackageApproved::dispatch($package);

            return $package;
        });
    }
}
