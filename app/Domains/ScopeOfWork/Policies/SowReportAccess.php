<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Policies;

use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;

/**
 * Scope and state predicates backing {@see SowReportPolicy}.
 *
 * Every decision here answers "is this user inside the report's boundary, and
 * is the report still mutable?" — the report-level abilities layer their
 * role rules on top. Kept separate so the policy class stays within the
 * method-count budget (php:S1448).
 */
final class SowReportAccess
{
    /** Only draft and in-review reports may still be acted upon. */
    public function isUnfinalized(SowReport $report): bool
    {
        return in_array($report->status, [SowStatus::DRAFT, SowStatus::IN_REVIEW], true);
    }

    /** Admin, the report's creator, or anyone with appropriate market/project access. */
    public function inMarket(User $user, SowReport $report): bool
    {
        if ($user->hasRole(Role::ADMIN->value) || $report->created_by === $user->id) {
            return true;
        }

        if (! $this->hasMarketAccess($user, $report->market_id)) {
            return false;
        }

        if ($report->project_id !== null) {
            return $this->hasProjectAccess($user, $report);
        }

        return $user->hasRole(Role::MANAGER->value)
            || ($user->hasRole(Role::CUSTOMER->value) && $report->status === SowStatus::PUBLISHED);
    }

    /** Admin or managing manager, or a supervisor tied to the report via authorship or project. */
    public function canManageOrOwnsReport(User $user, SowReport $report): bool
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

        if ($user->hasRole(Role::MANAGER->value)) {
            return $report->project_id === null || $this->hasProjectAccess($user, $report);
        }

        return $user->hasRole(Role::FIELD_SUPERVISOR->value)
            && ($report->created_by === $user->id || $this->hasProjectAccess($user, $report));
    }

    /**
     * Field supervisors own their drafts; managers and admins must not be able
     * to edit or publish a technician's untouched draft.
     */
    public function isCreatedByFieldSupervisor(SowReport $report): bool
    {
        /** @var User|null $creator */
        $creator = $report->relationLoaded('creator')
            ? $report->creator
            : $report->creator()->first();

        if ($creator === null) {
            return false;
        }

        return $creator->hasRole(Role::FIELD_SUPERVISOR->value);
    }

    /**
     * A draft is its author's working copy. A technician always sees their own,
     * but a manager's or admin's draft stays private until it is published — a
     * technician assigned to the project must not see work in progress.
     *
     * The write-side twin of this is {@see SowReportPolicy::canUpdateDraft},
     * which keeps a supervisor's draft editable by that supervisor alone.
     */
    public function isVisibleToTechnician(User $user, SowReport $report): bool
    {
        return $report->status !== SowStatus::DRAFT || $report->created_by === $user->id;
    }

    /**
     * Users with explicit market assignments are scoped to them; unassigned
     * staff fall back to global access, but customers never do.
     */
    private function hasMarketAccess(User $user, int $marketId): bool
    {
        if ($user->markets()->exists()) {
            return $user->markets()->where('markets.id', $marketId)->exists();
        }

        return ! $user->hasRole(Role::CUSTOMER->value);
    }

    private function hasProjectAccess(User $user, SowReport $report): bool
    {
        if ($report->project_id === null) {
            return false;
        }

        $project = $report->relationLoaded('project') ? $report->project : $report->project()->first();
        if ($project === null) {
            return false;
        }

        if ($user->hasRole(Role::CUSTOMER->value)) {
            return $project->customerAccesses()->where('user_id', $user->id)->exists();
        }

        return $project->manager_id === $user->id
            || $project->fieldSupervisors()->whereKey($user->id)->exists();
    }
}
