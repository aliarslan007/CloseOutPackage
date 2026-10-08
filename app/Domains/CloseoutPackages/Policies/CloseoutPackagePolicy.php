<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Policies;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CustomerPortal\Services\CustomerPortalScopeService;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;

class CloseoutPackagePolicy
{
    public function __construct(private readonly CustomerPortalScopeService $scopeService) {}

    public function view(User $user, CloseoutPackage $package): bool
    {
        if ($user->hasRole(Role::ADMIN->value) || $package->isLockedBy($user)) {
            return true;
        }

        // A locked package is inaccessible to everyone else.
        if ($package->isLocked()) {
            return false;
        }

        return match (true) {
            $user->hasRole(Role::CUSTOMER->value) => $this->scopeService->hasPackageAccess($user, $package),
            default => $this->canAccessProject($user, $package),
        };
    }

    private function canAccessProject(User $user, CloseoutPackage $package): bool
    {
        $project = $this->resolveProject($package);

        return $project !== null
            && ($project->manager_id === $user->id
                || $project->fieldSupervisors()->whereKey($user->id)->exists());
    }

    public function lock(User $user, CloseoutPackage $package): bool
    {
        // Locking is gated on full approval; anything else is an invalid state.
        if (! $package->isApproved()) {
            return false;
        }

        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

        return $user->hasRole(Role::MANAGER->value)
            && $this->resolveProject($package)?->manager_id === $user->id;
    }

    public function unlock(User $user, CloseoutPackage $package): bool
    {
        return $user->hasRole(Role::ADMIN->value) || $package->isLockedBy($user);
    }

    public function create(User $user, Project $project): bool
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            return false;
        }

        if ($user->hasRole(Role::MANAGER->value) && $project->manager_id === $user->id) {
            return true;
        }

        return $user->hasRole(Role::FIELD_SUPERVISOR->value)
            && $project->fieldSupervisors()->whereKey($user->id)->exists();
    }

    public function update(User $user, CloseoutPackage $package): bool
    {
        return $this->canEdit($user, $package);
    }

    /**
     * Package-level site notes and their images are a pre-approval concern:
     * the technician edits them in draft, the manager in draft or while
     * reviewing. Approved packages and the customer loop are read-only.
     */
    public function updateSiteNotes(User $user, CloseoutPackage $package): bool
    {
        if (! in_array($package->status, [
            PackageStatus::DRAFT,
            PackageStatus::SUBMITTED,
            PackageStatus::UNDER_REVIEW,
        ], true)) {
            return false;
        }

        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

        if ($package->isLocked()) {
            return false;
        }

        $project = $this->resolveProject($package);
        if ($project === null) {
            return false;
        }

        if ($user->hasRole(Role::FIELD_SUPERVISOR->value)) {
            return ($package->status === PackageStatus::DRAFT || $package->hasActiveFeedbackAssignedToTechnician())
                && ($package->created_by === $user->id
                    || $project->fieldSupervisors()->whereKey($user->id)->exists());
        }

        if ($user->hasRole(Role::MANAGER->value)) {
            return $project->manager_id === $user->id || $package->created_by === $user->id;
        }

        return false;
    }

    public function saveDraft(User $user, CloseoutPackage $package): bool
    {
        return $this->canEdit($user, $package);
    }

    public function submitStage(User $user, CloseoutPackage $package): bool
    {
        return $this->canEdit($user, $package);
    }

    public function submitPackage(User $user, CloseoutPackage $package): bool
    {
        return $this->canEdit($user, $package);
    }

    public function resubmit(User $user, CloseoutPackage $package): bool
    {
        return $this->canEdit($user, $package);
    }

    public function approve(User $user, CloseoutPackage $package): bool
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

        $project = $this->resolveProject($package);

        return $project !== null && $project->manager_id === $user->id;
    }

    public function reject(User $user, CloseoutPackage $package): bool
    {
        return $this->approve($user, $package);
    }

    public function delete(User $user, CloseoutPackage $package): bool
    {
        // Locked packages are admin-only for deletion.
        if ($package->isLocked() && ! $user->hasRole(Role::ADMIN->value)) {
            return false;
        }

        return match (true) {
            $user->hasRole(Role::ADMIN->value) => true,
            $user->hasRole(Role::MANAGER->value) => $this->resolveProject($package)?->manager_id === $user->id,
            $user->hasRole(Role::FIELD_SUPERVISOR->value) => $package->created_by === $user->id,
            default => false,
        };
    }

    private function canEdit(User $user, CloseoutPackage $package): bool
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

        $isFeedbackLoop = $package->isInCustomerReview()
            || $package->hasActiveFeedbackAssignedToTechnician()
            || $package->hasPendingFeedback()
            || in_array($package->status, [
                PackageStatus::FEEDBACK_RECEIVED,
                PackageStatus::REVISION_IN_PROGRESS,
                PackageStatus::CUSTOMER_REVIEW,
            ], true);

        if ($package->isLocked() && ! $isFeedbackLoop) {
            return false;
        }

        $project = $this->resolveProject($package);
        if ($project === null) {
            return false;
        }

        if ($user->hasRole(Role::FIELD_SUPERVISOR->value)) {
            return $package->created_by === $user->id
                || $project->fieldSupervisors()->whereKey($user->id)->exists();
        }

        if ($user->hasRole(Role::MANAGER->value)) {
            $isAssignedManager = $project->manager_id === $user->id || $package->created_by === $user->id;
            if (! $isAssignedManager) {
                return false;
            }

            return $isFeedbackLoop || $package->status === PackageStatus::DRAFT;
        }

        return false;
    }

    /**
     * Resolve the package's project, reusing the eager-loaded relation when
     * present so per-package authorization does not trigger an N+1 query.
     */
    private function resolveProject(CloseoutPackage $package): ?Project
    {
        return $package->relationLoaded('project')
            ? $package->getRelation('project')
            : Project::query()->find($package->project_id);
    }
}
