<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Policies;

use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\Projects\Models\Project;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class MediaAssetPolicy
{
    public function viewAny(): bool
    {
        return true; // Scope is applied in the query layer.
    }

    public function view(User $user, MediaAsset $asset): bool
    {
        return $this->canAccess($user, $asset) || $this->isViewableViaSowReport($user, $asset);
    }

    /**
     * Any staff member may upload; when a project is referenced they must be
     * the project's manager or an assigned field supervisor. Customers (portal
     * only) never upload into the media library.
     */
    public function create(User $user, ?Project $project = null): bool
    {
        if (! $user->hasAnyRole([
            Role::ADMIN->value,
            Role::MANAGER->value,
            Role::FIELD_SUPERVISOR->value,
        ])) {
            return false;
        }

        // Admins may always upload; an untagged upload has no project to scope to.
        if ($user->hasRole(Role::ADMIN->value) || $project === null) {
            return true;
        }

        return $project->manager_id === $user->id
            || $project->fieldSupervisors()->whereKey($user->id)->exists();
    }

    public function update(User $user, MediaAsset $asset): bool
    {
        return $this->canAccess($user, $asset);
    }

    public function delete(User $user, MediaAsset $asset): bool
    {
        // Field technicians may only delete their own uploads — never admin/manager media.
        if ($user->hasRole(Role::FIELD_SUPERVISOR->value)) {
            return $user->id === $asset->user_id;
        }

        return $this->canAccess($user, $asset);
    }

    public function assign(User $user, MediaAsset $asset): bool
    {
        return $this->canAccess($user, $asset);
    }

    private function canAccess(User $user, MediaAsset $asset): bool
    {
        return $this->isOwnerOrAdmin($user, $asset)
            || $this->isManagerOfAssetProject($user, $asset)
            || $this->isManagerOfUploader($user, $asset)
            || $this->isTechnicianOnAssetProject($user, $asset)
            || $this->isTechniciansManager($user, $asset)
            || $this->isUploaderAdmin($user, $asset);
    }

    private function isOwnerOrAdmin(User $user, MediaAsset $asset): bool
    {
        return $user->id === $asset->user_id || $user->hasRole(Role::ADMIN->value);
    }

    /**
     * A manager may act on assets tagged to projects they manage.
     */
    private function isManagerOfAssetProject(User $user, MediaAsset $asset): bool
    {
        if (! $user->hasRole(Role::MANAGER->value) || $asset->project_id === null) {
            return false;
        }

        return Project::query()
            ->whereKey($asset->project_id)
            ->where('manager_id', $user->id)
            ->exists();
    }

    /**
     * A manager may act on any assets uploaded by supervisors assigned to
     * projects they manage, even before the asset is assigned to a project.
     */
    private function isManagerOfUploader(User $user, MediaAsset $asset): bool
    {
        if (! $user->hasRole(Role::MANAGER->value)) {
            return false;
        }

        $managedProjects = Project::query()->select('id')->where('manager_id', $user->id);

        return DB::table('project_field_supervisors')
            ->whereIn('project_id', $managedProjects)
            ->where('user_id', $asset->user_id)
            ->exists();
    }

    /**
     * A field technician may act on assets tagged to projects they are
     * assigned to (e.g. their manager's uploads on those projects).
     */
    private function isTechnicianOnAssetProject(User $user, MediaAsset $asset): bool
    {
        if (! $user->hasRole(Role::FIELD_SUPERVISOR->value) || $asset->project_id === null) {
            return false;
        }

        return DB::table('project_field_supervisors')
            ->where('project_id', $asset->project_id)
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * A field technician may act on unassigned uploads from the managers of
     * their assigned projects (generic photos of their team's managers).
     */
    private function isTechniciansManager(User $user, MediaAsset $asset): bool
    {
        if (! $user->hasRole(Role::FIELD_SUPERVISOR->value) || $asset->project_id !== null) {
            return false;
        }

        $assignedProjectIds = DB::table('project_field_supervisors')
            ->where('user_id', $user->id)
            ->select('project_id');

        return Project::query()
            ->whereIn('id', $assignedProjectIds)
            ->where('manager_id', $asset->user_id)
            ->exists();
    }

    /**
     * Admin-uploaded assets are visible to any manager or field technician.
     */
    private function isUploaderAdmin(User $user, MediaAsset $asset): bool
    {
        if (! $user->hasAnyRole([Role::MANAGER->value, Role::FIELD_SUPERVISOR->value])) {
            return false;
        }

        return User::role(Role::ADMIN->value)->whereKey($asset->user_id)->exists();
    }

    /**
     * When an unassigned photo is attached to an SOW report (as an annotated
     * image or cover photo), any user authorized to view that SOW report
     * (e.g. project manager, admin, or customer for published reports) may
     * view the photo file.
     */
    private function isViewableViaSowReport(User $user, MediaAsset $asset): bool
    {
        if (! $user->hasAnyRole([Role::MANAGER->value, Role::CUSTOMER->value, Role::FIELD_SUPERVISOR->value])) {
            return false;
        }

        return SowReport::query()
            ->where(function (Builder $query) use ($asset): void {
                $query->where('cover_media_asset_id', $asset->id)
                    ->orWhereHas('annotatedImages', function (Builder $q) use ($asset): void {
                        $q->where('media_asset_id', $asset->id);
                    });
            })
            ->get()
            ->contains(fn (SowReport $report): bool => Gate::forUser($user)->allows('view', $report));
    }
}
