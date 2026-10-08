<?php

declare(strict_types=1);

namespace App\Domains\Projects\Actions;

use App\Domains\ChangeOrders\Actions\DetachChangeOrderPhotoAction;
use App\Domains\ChangeOrders\Models\ChangeOrderPhoto;
use App\Domains\CloseoutPackages\Actions\DeleteGarbagePackageAction;
use App\Domains\MediaLibrary\Actions\DeleteMediaAssetAction;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;
use App\Domains\ScopeOfWork\Actions\DeleteSowReportAction;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Shared\Exceptions\ProjectHasActivePackagesException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a project and everything hanging off it.
 *
 * This is the single owner of the cascade: Project is soft-deleted, so no
 * `ON DELETE CASCADE` in the schema ever fires and nothing else cleans up.
 * Keep it in sync with the tables carrying a project_id
 * (see docs/PROJECT_DELETE_CASCADE_AUDIT.md).
 *
 * Deliberately retained: email_logs (audit trail — the schema sets those to
 * NULL rather than cascading) and location_pings (pruned daily by
 * cop:prune-stale-location-pings).
 */
final class DeleteProjectAction
{
    public function __construct(
        private readonly DeleteSowReportAction $deleteSowReport,
        private readonly DeleteMediaAssetAction $deleteMediaAsset,
        private readonly DeleteGarbagePackageAction $purgePackage,
        private readonly DetachChangeOrderPhotoAction $detachChangeOrderPhoto,
    ) {}

    public function execute(Project $project): void
    {
        // Only ACTIVE packages block deletion — soft-deleted (trashed) ones
        // must not pin a project forever.
        if ($project->packages()->whereNull('closeout_packages.deleted_at')->exists()) {
            throw new ProjectHasActivePackagesException('This project has close-out packages and cannot be deleted.');
        }

        /** @var Collection<int, SowReport> $sowReports */
        $sowReports = $project->sowReports()->get();
        /** @var Collection<int, MediaAsset> $mediaAssets */
        $mediaAssets = $project->mediaAssets()->get();
        /** @var Collection<int, ChangeOrderPhoto> $changeOrderPhotos */
        $changeOrderPhotos = ChangeOrderPhoto::query()
            ->whereIn('change_order_id', $project->changeOrders()->select('id'))
            ->get();
        /** @var Collection<int, IssueHistory> $issues */
        $issues = $project->issues()->get();

        DB::transaction(function () use ($project): void {
            // Anything still here is already trashed (an active package threw
            // above), so purging it can never destroy live work. This is the
            // Deleted Packages screen's own purge: a real DELETE, which lets the
            // schema cascade take revisions, stages, notes and photos too.
            foreach ($project->packages()->onlyTrashed()->get() as $package) {
                $this->purgePackage->purge($package);
            }

            $project->changeOrders()->delete();
            $project->issues()->delete();
            $project->chatMessages()->delete();
            $project->customerAccesses()->delete();
            $project->crewAssignments()->delete();
            $project->sowReports()->delete();
            $project->mediaAssets()->delete();
            $project->fieldSupervisors()->detach();
            $project->delete();
        });

        // Bytes are released only after the rows above are gone: a rolled-back
        // transaction must never leave live records whose files were unlinked.
        foreach ($sowReports as $report) {
            $this->deleteSowReport->releaseStoredFiles($report);
        }

        foreach ($mediaAssets as $asset) {
            $this->deleteMediaAsset->releaseStoredFiles($asset);
        }

        // Change-order photos are a plain path column on the private disk (not
        // Spatie), and soft-deleting their order never cascades to the row or the
        // file. Reuse the change-order screen's own detach so both go together.
        foreach ($changeOrderPhotos as $photo) {
            $this->detachChangeOrderPhoto->execute($photo);
        }

        // Issues are hard-deleted by the mass query above, which skips Eloquent
        // events, so Spatie never gets to remove their attachments. Clear them
        // from the loaded models now that the rows are committed.
        foreach ($issues as $issue) {
            $issue->clearMediaCollection(IssueHistory::MEDIA_COLLECTION);
        }
    }
}
