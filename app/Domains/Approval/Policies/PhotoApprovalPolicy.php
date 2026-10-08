<?php

declare(strict_types=1);

namespace App\Domains\Approval\Policies;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;

class PhotoApprovalPolicy
{
    public function review(User $user, Photo $photo): bool
    {
        $package = CloseoutPackage::query()->find($photo->closeout_package_id);

        if ($package === null) {
            return false;
        }

        return $this->isAssignedManagerOrAdmin($user, $package);
    }

    public function bulkReview(User $user, CloseoutPackage $package): bool
    {
        return $this->isAssignedManagerOrAdmin($user, $package);
    }

    public function finalApprove(User $user, CloseoutPackage $package): bool
    {
        return $this->isAssignedManagerOrAdmin($user, $package);
    }

    private function isAssignedManagerOrAdmin(User $user, CloseoutPackage $package): bool
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

        if (! $user->hasRole(Role::MANAGER->value)) {
            return false;
        }

        $project = Project::find($package->project_id);

        return $project !== null && $project->manager_id === $user->id;
    }
}
