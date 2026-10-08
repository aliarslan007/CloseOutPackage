<?php

declare(strict_types=1);

namespace App\Domains\Photos\Actions;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\EntityFeedback\Support\AssertFeedbackTargetNotWithdrawn;
use App\Domains\Photos\Models\Photo;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Guards every photo-mutation flow against approved packages.
 *
 * Once a closeout package is approved its photos are final: reviewing,
 * replacing, or otherwise altering them afterwards is a logical error.
 * ReviewPhotoAction and ReplacePhotoAction both delegate here so the rule
 * lives in exactly one place.
 *
 * Given the photo being touched, it also refuses an item whose feedback the
 * customer withdrew. That rule is per-photo, not per-package, so it applies in
 * every status and to the photo's own category and section.
 */
final class AssertPackagePhotosEditableAction
{
    public const MESSAGE = 'This package is approved and its photos can no longer be modified.';

    public function execute(?CloseoutPackage $package, ?Photo $photo = null): void
    {
        // Checked before anything else: a photo the customer withdrew is off the
        // table whatever the package status is, and whatever a stale tab shows.
        if ($photo !== null) {
            app(AssertFeedbackTargetNotWithdrawn::class)->forPhoto($package, $photo);
        }

        if ($package === null || $package->status !== PackageStatus::APPROVED) {
            return;
        }

        $isFeedbackLoop = $package->isInCustomerReview()
            || $package->hasActiveFeedbackAssignedToTechnician()
            || $package->hasPendingFeedback()
            || in_array($package->status, [
                PackageStatus::FEEDBACK_RECEIVED,
                PackageStatus::REVISION_IN_PROGRESS,
                PackageStatus::CUSTOMER_REVIEW,
            ], true);

        if ($isFeedbackLoop) {
            return;
        }

        throw new InvalidStateTransitionException(self::MESSAGE);
    }
}
