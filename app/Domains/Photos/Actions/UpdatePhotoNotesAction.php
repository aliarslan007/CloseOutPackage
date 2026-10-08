<?php

declare(strict_types=1);

namespace App\Domains\Photos\Actions;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\EntityFeedback\Support\AssertFeedbackTargetNotWithdrawn;
use App\Domains\Photos\DTOs\UpdatePhotoNotesDTO;
use App\Domains\Photos\Models\Photo;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\InvalidStateTransitionException;

final class UpdatePhotoNotesAction
{
    public function execute(Photo $photo, UpdatePhotoNotesDTO $dto, User $user): Photo
    {
        $package = CloseoutPackage::query()->findOrFail($photo->closeout_package_id);

        if (! $user->hasRole(Role::ADMIN->value)) {
            $this->validatePackageState($package, $user);
        }

        // Nobody writes notes onto a photo the customer withdrew - including an
        // admin, whose package-state bypass is about status, not about consent.
        app(AssertFeedbackTargetNotWithdrawn::class)->forPhoto($package, $photo);

        $photo->update([
            'notes' => $dto->notes,
        ]);

        return $photo;
    }

    private function validatePackageState(CloseoutPackage $package, User $user): void
    {
        $isFeedbackLoop = $package->isInCustomerReview()
            || $package->hasActiveFeedbackAssignedToTechnician()
            || $package->hasPendingFeedback()
            || in_array($package->status, [
                PackageStatus::FEEDBACK_RECEIVED,
                PackageStatus::REVISION_IN_PROGRESS,
                PackageStatus::CUSTOMER_REVIEW,
            ], true);

        if ($package->isLocked() && ! $isFeedbackLoop) {
            throw new InvalidStateTransitionException('Photo notes cannot be modified on a locked package.');
        }

        if ($user->hasRole(Role::FIELD_SUPERVISOR->value)) {
            $isSupervisorAllowed = $isFeedbackLoop || $package->status === PackageStatus::DRAFT;

            if (! $isSupervisorAllowed) {
                throw new InvalidStateTransitionException(
                    'Field technicians can only modify photo notes while the package is in draft or revision.'
                );
            }

            return;
        }

        if ($user->hasRole(Role::MANAGER->value)) {
            // Managers may annotate in any unlocked status, including draft —
            // the policy already scopes them to projects they manage or
            // packages they created.
            return;
        }

        if ($package->status === PackageStatus::DRAFT) {
            throw new InvalidStateTransitionException(
                'Photo notes cannot be modified while the package is in draft.'
            );
        }
    }
}
