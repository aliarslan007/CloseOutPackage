<?php

declare(strict_types=1);

namespace App\Domains\Photos\Actions;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\EntityFeedback\Support\AssertFeedbackTargetNotWithdrawn;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Actions\UpdateProjectProgressAction;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class DeletePhotoAction
{
    public function execute(Photo $photo): void
    {
        $package = CloseoutPackage::query()->find($photo->closeout_package_id);

        $isFeedbackLoop = $package !== null && (
            $package->isInCustomerReview()
            || $package->hasActiveFeedbackAssignedToTechnician()
            || $package->hasPendingFeedback()
            || in_array($package->status, [
                PackageStatus::FEEDBACK_RECEIVED,
                PackageStatus::REVISION_IN_PROGRESS,
                PackageStatus::CUSTOMER_REVIEW,
            ], true)
        );

        if ($package === null || (! $isFeedbackLoop && $package->status !== PackageStatus::DRAFT)) {
            throw new InvalidStateTransitionException(
                'This package has been submitted and its photos can no longer be removed.',
            );
        }

        // The package may be open for work; a photo the customer withdrew is not.
        app(AssertFeedbackTargetNotWithdrawn::class)->forPhoto($package, $photo);

        DB::transaction(function () use ($photo, $package): void {
            $project = $package->project;

            $photo->clearMediaCollection(Photo::COLLECTION);
            $photo->delete();

            if ($project !== null) {
                app(UpdateProjectProgressAction::class)->execute($project);
            }

            activity('photo')
                ->causedBy(Auth::user())
                ->performedOn($package)
                ->event('photo.deleted')
                ->withProperties(['photo_id' => $photo->id, 'position' => $photo->position])
                ->log('Draft photo removed');
        });
    }
}
