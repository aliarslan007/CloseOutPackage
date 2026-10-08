<?php

declare(strict_types=1);

namespace App\Domains\CustomerPortal\Services;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\Customers\Models\CustomerEngineer;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

final class CustomerPortalScopeService
{
    /**
     * @return Builder<Project>
     */
    public function accessibleProjects(User $user): Builder
    {
        return Project::query()->whereIn('id', $this->accessibleProjectIds($user->id));
    }

    public function hasProjectAccess(User $user, Project $project): bool
    {
        return $this->activeRecord($user->id, $project->id) !== null
            || $this->isAssignedEngineerProject($user->id, $project->id);
    }

    public function hasPackageAccess(User $user, CloseoutPackage $package): bool
    {
        // Customers can never be the locker of a package, so a locked package is
        // inaccessible to every customer.
        if (! $this->isCustomerAccessibleStatus($package) || $package->isLocked()) {
            return false;
        }

        $project = Project::query()->find($package->project_id);

        return $project !== null && $this->hasProjectAccess($user, $project);
    }

    public function ensurePackageAccessible(User $user, CloseoutPackage $package): void
    {
        if (! $this->isCustomerAccessibleStatus($package) || $package->isLocked()) {
            abort(404);
        }

        $project = Project::query()->find($package->project_id);

        if ($project === null) {
            abort(404);
        }

        $this->ensureAccessible($user, $project);
    }

    public function ensureAccessible(User $user, Project $project): void
    {
        $record = CustomerProjectAccess::query()
            ->where('user_id', $user->id)
            ->where('project_id', $project->id)
            ->first();

        if ($record === null) {
            // Being the assigned customer engineer is access in its own right.
            if ($this->isAssignedEngineerProject($user->id, $project->id)) {
                return;
            }

            abort(404);
        }

        /** @var Carbon|null $expiresAt */
        $expiresAt = $record->expires_at;

        if ($expiresAt !== null && $expiresAt->isPast()) {
            abort(403, 'Your access to this project has expired. Please contact your administrator.');
        }
    }

    /**
     * Packages in the customer feedback & revision loop remain visible to
     * customers (plan §15): the loop extends APPROVED non-destructively.
     */
    private function isCustomerAccessibleStatus(CloseoutPackage $package): bool
    {
        return $package->isApproved() || $package->isInCustomerReview();
    }

    /**
     * All customers with active (non-expired) access to the project, for
     * feedback fan-out notifications (plan §14: rounds are shared per package).
     *
     * @return list<User>
     */
    public function customersWithAccess(Project $project): array
    {
        /** @var list<User> $customers */
        $customers = CustomerProjectAccess::query()
            ->where('project_id', $project->id)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->with('user')
            ->get()
            ->map(fn (CustomerProjectAccess $access): ?User => $access->user)
            ->filter()
            ->values()
            ->all();

        return $customers;
    }

    /**
     * @return list<int>
     */
    private function accessibleProjectIds(int $userId): array
    {
        $granted = CustomerProjectAccess::query()
            ->where('user_id', $userId)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->pluck('project_id')
            ->all();

        return array_values(array_unique([
            ...array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $granted),
            ...$this->assignedEngineerProjectIds($userId),
        ]));
    }

    /**
     * Projects whose assigned customer engineer signs in as this user —
     * their current workload, visible in the portal without a grant row.
     *
     * @return list<int>
     */
    private function assignedEngineerProjectIds(int $userId): array
    {
        /** @var list<int> $ids */
        $ids = Project::query()
            ->whereIn('customer_engineer_id', CustomerEngineer::query()
                ->where('user_id', $userId)
                ->select('id'))
            ->pluck('id')
            ->all();

        return $ids;
    }

    private function isAssignedEngineerProject(int $userId, int $projectId): bool
    {
        return in_array($projectId, $this->assignedEngineerProjectIds($userId), true);
    }

    private function activeRecord(int $userId, int $projectId): ?CustomerProjectAccess
    {
        return CustomerProjectAccess::query()
            ->where('user_id', $userId)
            ->where('project_id', $projectId)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->first();
    }
}
