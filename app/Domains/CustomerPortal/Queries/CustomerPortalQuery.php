<?php

declare(strict_types=1);

namespace App\Domains\CustomerPortal\Queries;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Queries\PackageQuery;
use App\Domains\EntityFeedback\Presenters\FeedbackRoundPresenter;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CustomerPortalQuery
{
    public function __construct(
        private readonly FeedbackRoundPresenter $feedbackPresenter,
        private readonly PackageQuery $packageQuery,
    ) {}

    public function projectDetails(Project $project, ?User $viewer = null): Project
    {
        $project = $project->load([
            'market',
            'manager.roles',
            'manager.media',
            'fieldSupervisors.roles',
            'fieldSupervisors.media',
            'media',
            'packages' => function (HasMany $query): void {
                $query->whereIn('status', [
                    PackageStatus::APPROVED->value,
                    PackageStatus::CUSTOMER_REVIEW->value,
                    PackageStatus::FEEDBACK_RECEIVED->value,
                    PackageStatus::REVISION_IN_PROGRESS->value,
                ])
                    ->orderBy('id')
                    ->with([
                        'template.sections.categories',
                        'template.checklistItems',
                        'latestFinalRevision',
                        'latestPartialRevision',
                        'activeFeedbackRound.notes.author',
                        'creator.roles',
                        'creator.media',
                        'lockedBy:id,name',
                        'stages',
                        'siteInfo',
                        'checklistResponses.checklistItem',
                        'photos' => function (HasMany $photoQuery): void {
                            $photoQuery->whereNull('replaced_at')
                                ->where('is_approved', true)
                                ->with([
                                    'stage',
                                    'categorySelection.templatePictureCategory.section',
                                    'uploader.roles',
                                    'uploader.media',
                                    'media',
                                    'replacedPhoto:id,replaced_by_photo_id',
                                ]);
                        },
                    ]);
            },
        ]);

        // ponytail: customer portal must not expose field tech email – hide at query level
        $project->fieldSupervisors->each(fn (User $u) => $u->makeHidden(['email']));

        $project->packages->each(function (CloseoutPackage $package) use ($viewer): void {
            // Site-note images ride along read-only in the "Additional Notes
            // and Comments" block, same strip the manager/tech editor uses.
            $this->packageQuery->attachPackageNoteImages($package);

            $presentedRound = $this->feedbackPresenter->present(
                $package->getRelation('activeFeedbackRound'),
                $viewer
            );
            $presentedHistory = $this->feedbackPresenter->presentAll(
                $package->feedbackRoundHistory(),
                $viewer
            );

            $this->feedbackPresenter->attachTo(
                $package,
                'feedback_round',
                $package->getRelation('activeFeedbackRound'),
                $viewer,
            );
            $package->setAttribute('feedbackRound', $presentedRound);
            $package->setAttribute('feedback_round_history', $presentedHistory);
            $package->setAttribute('feedbackRoundHistory', $presentedHistory);
        });

        return $project;
    }

    public function packageDetails(CloseoutPackage $package, ?User $viewer = null): CloseoutPackage
    {
        return $package->load([
            'project.market',
            'project.manager.roles',
            'project.manager.media',
            'template.sections.categories',
            'creator.roles',
            'creator.media',
            'latestFinalRevision',
            'latestPartialRevision',
            'activeFeedbackRound.notes.author',
            'stages',
            'photos' => function (HasMany $query): void {
                $query->whereNull('replaced_at')
                    ->where('is_approved', true)
                    ->with([
                        'stage',
                        'categorySelection.templatePictureCategory.section',
                        'uploader.roles',
                        'uploader.media',
                        'media',
                        'replacedPhoto:id,replaced_by_photo_id',
                    ]);
            },
        ]);
    }

    /**
     * @return Collection<int, Photo>
     */
    public function photos(CloseoutPackage $package): Collection
    {
        if (! ($package->isApproved() || $package->isInCustomerReview())) {
            return new Collection;
        }

        return Photo::query()
            ->where('closeout_package_id', $package->id)
            ->whereNull('replaced_at')
            ->where('is_approved', true)
            ->with([
                'stage',
                'categorySelection.templatePictureCategory.section',
                'uploader.roles',
                'uploader.media',
            ])
            ->get();
    }

    /**
     * @return Collection<int, Photo>
     */
    public function approvedPhotos(CloseoutPackage $package): Collection
    {
        return $this->photos($package);
    }
}
