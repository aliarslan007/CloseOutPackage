<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Policies;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;

class PdfGenerationPolicy
{
    public function generateFinal(User $user, CloseoutPackage $package): bool
    {
        return $this->isAssignedManagerOrAdmin($user, $package);
    }

    public function generatePartial(User $user, CloseoutPackage $package): bool
    {
        return $this->isAssignedManagerOrAdmin($user, $package);
    }

    public function download(User $user, CloseoutPackage $package): bool
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

        if ($this->lockBlocks($user, $package)) {
            return false;
        }

        $project = Project::find($package->project_id);

        if ($project === null) {
            return false;
        }

        return $project->manager_id === $user->id
            || $project->fieldSupervisors()->whereKey($user->id)->exists();
    }

    public function emailFinalPdf(User $user, CloseoutPackage $package): bool
    {
        return ! $this->lockBlocks($user, $package)
            && $this->isAssignedManagerOrAdmin($user, $package);
    }

    public function emailProgressPdf(User $user, CloseoutPackage $package): bool
    {
        return ! $this->lockBlocks($user, $package)
            && $this->isAssignedManagerOrAdmin($user, $package);
    }

    /**
     * Locked packages are only accessible to admins and the user who locked them.
     */
    private function lockBlocks(User $user, CloseoutPackage $package): bool
    {
        return $package->isLocked()
            && ! $user->hasRole(Role::ADMIN->value)
            && ! $package->isLockedBy($user);
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
