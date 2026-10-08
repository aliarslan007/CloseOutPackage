<?php

declare(strict_types=1);

namespace App\Domains\Projects\Listeners;

use App\Domains\CloseoutPackages\Events\PackageApproved;
use App\Domains\CloseoutPackages\Events\PackageCreated;
use App\Domains\CloseoutPackages\Events\PackageRejected;
use App\Domains\CloseoutPackages\Events\PackageResubmitted;
use App\Domains\CloseoutPackages\Events\PackageSubmitted;
use App\Domains\CloseoutPackages\Events\StageSubmitted;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\EntityFeedback\Events\FeedbackForwardedToTechnician;
use App\Domains\EntityFeedback\Events\FeedbackResolvedByManager;
use App\Domains\EntityFeedback\Events\FeedbackSubmittedByCustomer;
use App\Domains\EntityFeedback\Services\FeedbackEntityResolver;
use App\Domains\Photos\Events\PhotoReplaced;
use App\Domains\Photos\Events\PhotoUploaded;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Actions\UpdateProjectProgressAction;
use App\Domains\Projects\Events\ProjectStatusChanged;
use App\Domains\Projects\Models\Project;
use Illuminate\Support\Facades\Cache;

class InvalidateAndRecalculateProgressListener
{
    public function handle(
        StageSubmitted|PackageApproved|PackageRejected|PackageSubmitted|PackageResubmitted|FeedbackSubmittedByCustomer|FeedbackForwardedToTechnician|FeedbackResolvedByManager|ProjectStatusChanged|PhotoUploaded|PhotoReplaced|PackageCreated $event,
    ): void {
        $projectId = $this->resolveProjectId($event);

        if ($projectId === null) {
            return;
        }

        if (Cache::supportsTags()) {
            Cache::tags(['progress', "project.{$projectId}"])->flush();
        }

        $project = Project::find($projectId);

        if ($project !== null) {
            app(UpdateProjectProgressAction::class)->execute($project);
        }
    }

    private function resolveProjectId(object $event): ?int
    {
        if ($event instanceof ProjectStatusChanged) {
            return $event->project->id;
        }

        if ($event instanceof PhotoUploaded || $event instanceof PhotoReplaced) {
            return $this->projectIdForPhoto($event instanceof PhotoUploaded ? $event->photo : $event->newPhoto);
        }

        if ($event instanceof FeedbackSubmittedByCustomer
            || $event instanceof FeedbackForwardedToTechnician
            || $event instanceof FeedbackResolvedByManager) {
            return app(FeedbackEntityResolver::class)->projectFor($event->round)?->id;
        }

        return $event->package->project_id ?? null;
    }

    private function projectIdForPhoto(Photo $photo): ?int
    {
        $package = CloseoutPackage::query()->find($photo->closeout_package_id);

        return $package?->project_id;
    }
}
