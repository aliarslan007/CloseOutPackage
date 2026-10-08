<?php

declare(strict_types=1);

namespace App\Domains\Projects\Policies;

use App\Domains\CustomerPortal\Services\CustomerPortalScopeService;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;

class IssueHistoryPolicy
{
    public function __construct(private readonly CustomerPortalScopeService $scopeService) {}

    public function view(User $user, Project $project): bool
    {
        if ($user->hasRole(Role::CUSTOMER->value)) {
            return $this->scopeService->hasProjectAccess($user, $project);
        }

        return $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value])
            || $project->fieldSupervisors()->whereKey($user->id)->exists();
    }

    public function create(User $user, Project $project): bool
    {
        return $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value])
            || $project->fieldSupervisors()->whereKey($user->id)->exists();
    }

    public function resolve(User $user, IssueHistory $issue, Project $project): bool
    {
        if ($issue->project_id !== $project->id) {
            return false;
        }

        return $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]);
    }

    public function addComment(User $user, IssueHistory $issue, Project $project): bool
    {
        if ($issue->project_id !== $project->id || $issue->resolved_at !== null) {
            return false;
        }

        return $user->hasRole(Role::ADMIN->value)
            || ($user->hasRole(Role::MANAGER->value) && $project->manager_id === $user->id)
            || ($user->hasRole(Role::FIELD_SUPERVISOR->value) && $project->fieldSupervisors()->whereKey($user->id)->exists());
    }

    /**
     * Who may add, remove or replace an issue's attachments after creation:
     * admins and managers on any issue, field supervisors on their own.
     * Resolved issues are locked unless issues.allow_media_edit_when_resolved.
     */
    public function manageMedia(User $user, IssueHistory $issue, Project $project): bool
    {
        if ($issue->project_id !== $project->id) {
            return false;
        }

        if ($issue->resolved_at !== null && ! (bool) config('issues.allow_media_edit_when_resolved', false)) {
            return false;
        }

        if ($user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value])) {
            return true;
        }

        return $user->hasRole(Role::FIELD_SUPERVISOR->value)
            && $issue->reported_by === $user->id;
    }

    public function delete(User $user, IssueHistory $issue, Project $project): bool
    {
        if ($issue->project_id !== $project->id) {
            return false;
        }

        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

        return $issue->reported_by === $user->id
            && $user->hasRole(Role::MANAGER->value);
    }
}
