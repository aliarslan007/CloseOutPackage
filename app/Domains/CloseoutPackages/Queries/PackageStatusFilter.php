<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Queries;

use App\Domains\Approval\Services\ApprovalReadinessService;
use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Photos\Models\Photo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Single source of truth for the package status buckets used by both the
 * global dashboard packages page and the per-project packages page:
 *
 *   awaiting_review  = submitted + under review
 *   needs_rework     = rejected  OR  any active flagged/replacement photo
 *   ready_to_submit  = draft with all supervisor-controlled work complete
 *   anything else    = a raw PackageStatus value
 */
final class PackageStatusFilter
{
    /**
     * Readiness rules the field supervisor controls before submitting. The
     * full readiness report also covers manager-side review, which can never
     * pass while a package is still a draft.
     *
     * @var list<string>
     */
    private const SUPERVISOR_CONTROLLED_RULES = [
        'site_info_complete',
        'checklist_answered',
        'photos_uploaded',
    ];

    public function __construct(private readonly ApprovalReadinessService $readinessService) {}

    /**
     * Apply the status bucket to the working query. The id-based buckets
     * (needs_rework, ready_to_submit) compute their package sets from the
     * unfiltered scoped base query.
     *
     * @param  Builder<CloseoutPackage>  $query
     * @param  Builder<CloseoutPackage>  $base
     */
    public function apply(Builder $query, Builder $base, ?string $status): void
    {
        if ($status === 'awaiting_review') {
            $query->whereIn('status', [
                PackageStatus::SUBMITTED->value,
                PackageStatus::UNDER_REVIEW->value,
            ]);
        } elseif ($status === 'needs_rework') {
            $flaggedIds = $this->flaggedPackageIds($base);

            $query->where(function (Builder $q) use ($flaggedIds): void {
                $q->where('status', PackageStatus::REJECTED->value);

                if ($flaggedIds !== []) {
                    $q->orWhereIn('id', $flaggedIds);
                }
            });
        } elseif ($status === 'ready_to_submit') {
            $query->whereIn('id', $this->readyToSubmitIds($base));
        } elseif (is_string($status) && PackageStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        }
    }

    /**
     * Whether the value is a known status bucket or a raw package status.
     */
    public function supports(string $status): bool
    {
        return in_array($status, ['awaiting_review', 'needs_rework', 'ready_to_submit'], true)
            || PackageStatus::tryFrom($status) !== null;
    }

    /**
     * Ids of packages with an active flagged or replacement-requested photo.
     *
     * @param  Builder<CloseoutPackage>  $base
     * @param  list<int>|null  $preloadedPackageIds
     * @return list<int>
     */
    public function flaggedPackageIds(Builder $base, ?array $preloadedPackageIds = null): array
    {
        $packageIds = $preloadedPackageIds ?? (clone $base)->pluck('id')->all();

        if ($packageIds === []) {
            return [];
        }

        /** @var list<int> $ids */
        $ids = Photo::query()
            ->whereIn('closeout_package_id', $packageIds)
            ->whereNull('replaced_at')
            ->whereIn('status', [PhotoStatus::FLAGGED->value, PhotoStatus::REPLACEMENT_REQUESTED->value])
            ->pluck('closeout_package_id')
            ->unique()
            ->values()
            ->all();

        return $ids;
    }

    /**
     * Ids of draft packages whose supervisor-controlled work is complete.
     *
     * @param  Builder<CloseoutPackage>  $base
     * @return list<int>
     */
    public function readyToSubmitIds(Builder $base): array
    {
        /** @var Collection<int, CloseoutPackage> $drafts */
        $drafts = (clone $base)
            ->where('status', PackageStatus::DRAFT->value)
            ->with(['stages.categorySelections', 'checklistResponses.checklistItem'])
            ->get()
            ->filter(fn (CloseoutPackage $draft): bool => $this->isReadyToSubmit($draft));

        /** @var list<int> $ids */
        $ids = $drafts->pluck('id')->all();

        return $ids;
    }

    private function isReadyToSubmit(CloseoutPackage $package): bool
    {
        $report = $this->readinessService->check($package);

        foreach ($report->failures as $failure) {
            if (in_array($failure->rule, self::SUPERVISOR_CONTROLLED_RULES, true)) {
                return false;
            }
        }

        return true;
    }
}
