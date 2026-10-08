<?php

declare(strict_types=1);

namespace App\Domains\Photos\Actions;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\MarketAccessDeniedException;

final class ValidatePhotoOwnershipAction
{
    /**
     * @param  bool  $allowRevisionManager  Permit the assigned project manager to
     *                                      mutate photos while the package is in
     *                                      the customer feedback / revision loop.
     */
    public function execute(CloseoutPackage $package, User $user, bool $allowRevisionManager = false): void
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            return;
        }

        $project = Project::find($package->project_id);

        if ($project === null) {
            throw new MarketAccessDeniedException('You do not have access to this project.');
        }

        $isFeedbackLoop = $allowRevisionManager
            || $package->isInCustomerReview()
            || $package->hasActiveFeedbackAssignedToTechnician()
            || $package->hasPendingFeedback()
            || in_array($package->status, [
                PackageStatus::FEEDBACK_RECEIVED,
                PackageStatus::REVISION_IN_PROGRESS,
                PackageStatus::CUSTOMER_REVIEW,
            ], true);

        if ($user->hasRole(Role::MANAGER->value)
            && ($project->manager_id === $user->id || $package->created_by === $user->id)
            && (! $package->isLocked() || $isFeedbackLoop)
            && ($isFeedbackLoop || $package->status === PackageStatus::DRAFT)) {
            return;
        }

        $assigned = $project->fieldSupervisors()->whereKey($user->id)->exists();

        if (! $assigned) {
            throw new MarketAccessDeniedException('You do not have access to this project.');
        }
    }
}
