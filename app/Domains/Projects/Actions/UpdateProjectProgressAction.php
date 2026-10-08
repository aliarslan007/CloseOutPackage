<?php

declare(strict_types=1);

namespace App\Domains\Projects\Actions;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Services\ProjectProgressService;
use Illuminate\Support\Facades\Cache;

final class UpdateProjectProgressAction
{
    private const CACHE_TTL_SECONDS = 300;

    public function execute(Project $project): void
    {
        $progress = app(ProjectProgressService::class)->calculate($project);

        $hasApprovedPackage = $project->packages()
            ->whereIn('status', [PackageStatus::APPROVED->value, PackageStatus::CUSTOMER_REVIEW->value])
            ->exists();

        $hasSubmittedPackage = $project->packages()
            ->where('status', PackageStatus::SUBMITTED->value)
            ->exists();

        $hasRejectedPackage = $project->packages()
            ->where('status', PackageStatus::REJECTED->value)
            ->exists();

        $hasPackages = $project->packages()->exists();

        $updateData = ['progress_percentage' => $progress];

        if ($project->status !== ProjectStatus::ARCHIVED) {
            if ($hasApprovedPackage || $progress === 100) {
                $updateData['status'] = ProjectStatus::COMPLETED->value;
            } elseif ($hasSubmittedPackage) {
                $updateData['status'] = ProjectStatus::SUBMITTED->value;
            } elseif ($hasRejectedPackage) {
                $updateData['status'] = ProjectStatus::REJECTED->value;
            } elseif ($progress > 0 || $hasPackages) {
                $updateData['status'] = ProjectStatus::IN_PROGRESS->value;
            } else {
                $updateData['status'] = ProjectStatus::PENDING->value;
            }
        }

        $project->update($updateData);

        if (Cache::supportsTags()) {
            Cache::tags(['progress', "project.{$project->id}"])
                ->put('progress', $progress, self::CACHE_TTL_SECONDS);
        }
    }
}
