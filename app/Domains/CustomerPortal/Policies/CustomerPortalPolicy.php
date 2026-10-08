<?php

declare(strict_types=1);

namespace App\Domains\CustomerPortal\Policies;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CustomerPortal\Services\CustomerPortalScopeService;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;

class CustomerPortalPolicy
{
    public function __construct(private readonly CustomerPortalScopeService $scopeService) {}

    public function viewProject(User $user, Project $project): bool
    {
        return $this->scopeService->hasProjectAccess($user, $project);
    }

    public function viewPackage(User $user, CloseoutPackage $package): bool
    {
        return $this->scopeService->hasPackageAccess($user, $package);
    }

    public function downloadPdf(User $user, CloseoutPackage $package): bool
    {
        return $this->viewPackage($user, $package);
    }
}
