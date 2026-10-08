<?php

declare(strict_types=1);

namespace App\Domains\Dashboard\Listeners;

use App\Domains\Approval\Events\PhotoReviewed;
use App\Domains\Approval\Events\ReplacementRequested;
use App\Domains\CloseoutPackages\Events\PackageApproved;
use App\Domains\CloseoutPackages\Events\PackageCreated;
use App\Domains\CloseoutPackages\Events\PackageDraftSaved;
use App\Domains\CloseoutPackages\Events\PackageRejected;
use App\Domains\CloseoutPackages\Events\PackageResubmitted;
use App\Domains\CloseoutPackages\Events\PackageSubmitted;
use App\Domains\CloseoutPackages\Events\StageSubmitted;
use App\Domains\Email\Events\EmailDelivered;
use App\Domains\Email\Events\EmailFailed;
use App\Domains\EntityFeedback\Events\FeedbackForwardedToTechnician;
use App\Domains\EntityFeedback\Events\FeedbackResolvedByManager;
use App\Domains\EntityFeedback\Events\FeedbackSubmittedByCustomer;
use App\Domains\Photos\Events\PhotoReplaced;
use App\Domains\Photos\Events\PhotoUploaded;
use App\Domains\Projects\Events\FieldSupervisorAssigned;
use App\Domains\Projects\Events\FieldSupervisorRemoved;
use App\Domains\Projects\Events\IssueRecorded;
use App\Domains\Projects\Events\ProjectCreated;
use App\Domains\Projects\Events\ProjectManagerAssigned;
use App\Domains\Projects\Events\ProjectManagerRemoved;
use App\Domains\Projects\Events\ProjectStatusChanged;
use Illuminate\Support\Facades\Cache;
use Throwable;

class InvalidateDashboardCacheListener
{
    public function handle(
        StageSubmitted|PackageApproved|PackageSubmitted|ProjectStatusChanged|PackageCreated|PackageRejected|PackageResubmitted|PackageDraftSaved|FeedbackSubmittedByCustomer|FeedbackForwardedToTechnician|FeedbackResolvedByManager|PhotoUploaded|PhotoReplaced|ProjectCreated|ProjectManagerAssigned|ProjectManagerRemoved|FieldSupervisorAssigned|FieldSupervisorRemoved|IssueRecorded|EmailDelivered|EmailFailed|PhotoReviewed|ReplacementRequested $event,
    ): void {
        if (! Cache::supportsTags()) {
            return;
        }

        try {
            Cache::tags(['dashboard'])->flush();
        } catch (Throwable) {
            // Dashboard freshness is best-effort: a cache-store blip must
            // never fail the mutation that triggered this event. Stale stats
            // self-heal when the cached entry expires.
        }
    }
}
