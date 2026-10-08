<?php

declare(strict_types=1);

namespace App\Domains\Photos\Policies;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CustomerPortal\Services\CustomerPortalScopeService;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;

class PhotoPolicy
{
    public function __construct(private readonly CustomerPortalScopeService $scopeService) {}

    public function view(User $user, CloseoutPackage $package): bool
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

        return match (true) {
            $user->hasRole(Role::CUSTOMER->value) => $this->scopeService->hasPackageAccess($user, $package),
            default => $this->canAccessProject($user, $package),
        };
    }

    private function canAccessProject(User $user, CloseoutPackage $package): bool
    {
        $project = Project::find($package->project_id);

        return $project !== null
            && ($project->manager_id === $user->id
                || $project->fieldSupervisors()->whereKey($user->id)->exists());
    }

    public function viewPhoto(User $user, Photo $photo): bool
    {
        $package = CloseoutPackage::query()->find($photo->closeout_package_id);

        if ($package === null) {
            return false;
        }

        // Customers may only view approved, unreplaced photos in fully approved packages
        // for projects they have active access to.
        if ($user->hasRole(Role::CUSTOMER->value)) {
            return $photo->isApproved()
                && $photo->replaced_at === null
                && $this->scopeService->hasPackageAccess($user, $package);
        }

        return $this->view($user, $package);
    }

    public function create(User $user, CloseoutPackage $package): bool
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

        $project = Project::find($package->project_id);
        if ($project === null) {
            return false;
        }

        if (
            $user->hasRole(Role::MANAGER->value)
            && ($project->manager_id === $user->id || $package->created_by === $user->id)
            && in_array($package->status, [
                PackageStatus::DRAFT,
                PackageStatus::FEEDBACK_RECEIVED,
                PackageStatus::REVISION_IN_PROGRESS,
            ], true)
        ) {
            return true;
        }

        if (! $user->hasRole(Role::FIELD_SUPERVISOR->value)) {
            return false;
        }

        return $project->fieldSupervisors()->whereKey($user->id)->exists();
    }

    public function replace(User $user, Photo $photo): bool
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

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

        if ($package === null || ($package->isLocked() && ! $isFeedbackLoop)) {
            return false;
        }

        $project = Project::find($package->project_id);
        if ($project === null) {
            return false;
        }

        // Field technicians may replace any photo while the package is still a
        // draft, photos flagged for replacement once it is submitted, and any
        // photo while the package is in customer feedback revision / rework.
        if (
            $user->hasRole(Role::FIELD_SUPERVISOR->value)
            && $project->fieldSupervisors()->whereKey($user->id)->exists()
        ) {
            return $package->status === PackageStatus::DRAFT
                || $photo->isReplaceable()
                || $isFeedbackLoop;
        }

        // The assigned project manager or creator may swap any photo while the package is in DRAFT
        // or while the package is in the customer feedback / revision loop.
        return $user->hasRole(Role::MANAGER->value)
            && ($project->manager_id === $user->id || $package->created_by === $user->id)
            && ($package->status === PackageStatus::DRAFT || $isFeedbackLoop);
    }

    public function delete(User $user, Photo $photo): bool
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

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

        if ($package === null || ($package->isLocked() && ! $isFeedbackLoop)) {
            return false;
        }

        $project = Project::find($package->project_id);

        if (
            $user->hasRole(Role::FIELD_SUPERVISOR->value)
            && $project !== null
            && $project->fieldSupervisors()->whereKey($user->id)->exists()
            && $isFeedbackLoop
        ) {
            return true;
        }

        if (
            $user->hasRole(Role::MANAGER->value)
            && $project !== null
            && ($project->manager_id === $user->id || $package->created_by === $user->id || $photo->uploaded_by === $user->id)
            && ($package->status === PackageStatus::DRAFT || $isFeedbackLoop)
        ) {
            return true;
        }

        if ($package->status !== PackageStatus::DRAFT) {
            return false;
        }

        return $photo->uploaded_by === $user->id
            && ($photo->isPending() || $this->isSelfApproved($photo));
    }

    /**
     * A self-approved upload (e.g. a manager's own auto-approved photo)
     * carries no one else's review, so the uploader may still remove a
     * wrongly uploaded image. Manager-approved technician photos stay
     * protected.
     */
    private function isSelfApproved(Photo $photo): bool
    {
        return $photo->approved_by !== null
            && (int) $photo->approved_by === (int) $photo->uploaded_by;
    }

    public function updateNotes(User $user, Photo $photo): bool
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

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

        if ($package === null || ($package->isLocked() && ! $isFeedbackLoop)) {
            return false;
        }

        return $this->canActorUpdatePhotoNotes($user, $package);
    }

    private function canActorUpdatePhotoNotes(User $user, CloseoutPackage $package): bool
    {
        $project = Project::find($package->project_id);
        if ($project === null) {
            return false;
        }

        $isSupervisorEditable = $user->hasRole(Role::FIELD_SUPERVISOR->value)
            && ($package->status === PackageStatus::DRAFT
                || $package->hasActiveFeedbackAssignedToTechnician()
                || $package->isInCustomerReview()
                || in_array($package->status, [
                    PackageStatus::FEEDBACK_RECEIVED,
                    PackageStatus::REVISION_IN_PROGRESS,
                ], true))
            && $project->fieldSupervisors()->whereKey($user->id)->exists();

        $isManagerReview = $user->hasRole(Role::MANAGER->value)
            && ($project->manager_id === $user->id || $package->created_by === $user->id);

        return $isSupervisorEditable || $isManagerReview;
    }

    public function viewGps(User $user): bool
    {
        return $user->hasAnyRole([
            Role::ADMIN->value,
            Role::MANAGER->value,
            Role::FIELD_SUPERVISOR->value,
        ]);
    }
}
