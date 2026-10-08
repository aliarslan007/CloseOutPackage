<?php

declare(strict_types=1);

namespace App\Domains\Projects\Services;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use Illuminate\Database\Eloquent\Collection;

final class ProjectProgressService
{
    /**
     * Calculate the project progress percentage (0-100) as the share of
     * uploaded photos versus the photos required across all active packages
     * of the project.
     *
     * Every uploaded photo counts (pending, flagged, rejected, approved) as
     * long as it has not been replaced. Replacing a photo marks the previous
     * one as replaced, so the uploaded count never exceeds the required slots.
     */
    public function calculate(Project $project): int
    {
        $packages = $project->packages()->get();

        $hasApprovedPackage = $packages->isNotEmpty()
            && $packages->contains(fn (CloseoutPackage $pkg): bool => $pkg->isApprovedOrInCustomerReview());

        return match (true) {
            $packages->isEmpty() => 0,
            $hasApprovedPackage => 100,
            default => $this->photoProgress($packages),
        };
    }

    /**
     * @param  Collection<int, CloseoutPackage>  $packages
     */
    private function photoProgress(Collection $packages): int
    {
        $packageIds = $packages->pluck('id');

        $stageIds = PackageStage::query()
            ->whereIn('closeout_package_id', $packageIds)
            ->pluck('id');

        // Exclude optional picture categories (is_required = false) from required formula
        $required = PackageCategorySelection::query()
            ->whereIn('package_stage_id', $stageIds)
            ->where('is_selected', true)
            ->whereHas('templatePictureCategory', function ($query): void {
                $query->where('is_required', true)->whereNull('deleted_at');
            })
            ->sum('picture_count');

        if ($required === 0) {
            return 0;
        }

        $uploaded = Photo::query()
            ->whereIn('closeout_package_id', $packageIds)
            ->whereNull('replaced_at')
            ->count();

        return min((int) round(($uploaded / $required) * 100), 100);
    }
}
