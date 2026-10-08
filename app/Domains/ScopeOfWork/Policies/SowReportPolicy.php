<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Policies;

use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowAnnotatedImage;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;

class SowReportPolicy
{
    public function __construct(private readonly SowReportAccess $access) {}

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            Role::ADMIN->value,
            Role::MANAGER->value,
            Role::FIELD_SUPERVISOR->value,
            Role::CUSTOMER->value, // market-scoped list
        ]);
    }

    /** Admin, manager, and field supervisor can create SOW reports. */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            Role::ADMIN->value,
            Role::MANAGER->value,
            Role::FIELD_SUPERVISOR->value,
        ]);
    }

    /**
     * View rules:
     * - CUSTOMER: published only (§8).
     * - FIELD_SUPERVISOR: anything on their projects except someone else's
     *   draft. Their own draft is theirs, and a manager's or admin's draft is
     *   their working copy until published.
     * - ADMIN / MANAGER: anything inside their market and project scope.
     */
    public function view(User $user, SowReport $report): bool
    {
        if (! $this->access->inMarket($user, $report)) {
            return false;
        }

        if ($user->hasRole(Role::CUSTOMER->value)) {
            return $report->status === SowStatus::PUBLISHED;
        }

        if ($user->hasRole(Role::FIELD_SUPERVISOR->value)) {
            return $this->access->isVisibleToTechnician($user, $report);
        }

        return true;
    }

    /**
     * Update rule:
     * - DRAFT:
     *   - If created by a field supervisor: ONLY that supervisor can update their draft.
     *     Managers and admins see it in read-only mode until submitted.
     *   - If authored by manager or admin: creator or managing admin can edit.
     * - IN_REVIEW:
     *   - Managers and Admins in market can edit survey data and photos before approval.
     *     Field supervisors cannot edit once submitted.
     */
    public function update(User $user, SowReport $report): bool
    {
        if (! $this->access->isUnfinalized($report) || ! $this->access->inMarket($user, $report)) {
            return false;
        }

        return match ($report->status) {
            SowStatus::DRAFT => $this->canUpdateDraft($user, $report),
            SowStatus::IN_REVIEW => $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]),
            default => false,
        };
    }

    /** A field supervisor's draft belongs to that supervisor alone. */
    private function canUpdateDraft(User $user, SowReport $report): bool
    {
        if ($this->access->isCreatedByFieldSupervisor($report)) {
            return $user->hasRole(Role::FIELD_SUPERVISOR->value) && $report->created_by === $user->id;
        }

        return $this->access->canManageOrOwnsReport($user, $report);
    }

    /**
     * Submit rule:
     * - Draft only.
     * - Only field supervisors who created the report can submit for review.
     * - Managers and admins never submit for review.
     */
    public function submit(User $user, SowReport $report): bool
    {
        if ($report->status !== SowStatus::DRAFT) {
            return false;
        }

        if (! $this->access->inMarket($user, $report)) {
            return false;
        }

        return $user->hasRole(Role::FIELD_SUPERVISOR->value) && $report->created_by === $user->id;
    }

    /**
     * Publish (Approve & Finalize) rule:
     * - Manager or Admin only.
     * - In IN_REVIEW status: allowed for manager and admin in market.
     * - In DRAFT status:
     *   - CANNOT publish a field supervisor's draft (technician must submit first).
     *   - Allowed only if the manager/admin authored it directly.
     */
    public function publish(User $user, SowReport $report): bool
    {
        if (
            ! $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value])
            || ! $this->access->inMarket($user, $report)
        ) {
            return false;
        }

        return match ($report->status) {
            SowStatus::IN_REVIEW => true,
            SowStatus::DRAFT => $this->canPublishDraft($user, $report),
            default => false,
        };
    }

    /**
     * A technician's draft must be submitted, never published out from under
     * them; a manager/admin may publish only a draft they authored.
     */
    private function canPublishDraft(User $user, SowReport $report): bool
    {
        if ($this->access->isCreatedByFieldSupervisor($report)) {
            return false;
        }

        return $report->created_by === $user->id || $user->hasRole(Role::ADMIN->value);
    }

    /** Published or in-review; manager/admin (reopen path). */
    public function reopen(User $user, SowReport $report): bool
    {
        if (! in_array($report->status, [SowStatus::PUBLISHED, SowStatus::IN_REVIEW], true)) {
            return false;
        }

        if (! $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value])) {
            return false;
        }

        return $this->access->inMarket($user, $report);
    }

    /** §8 — admin only, and only when the state machine allows archiving. */
    public function archive(User $user, SowReport $report): bool
    {
        return $user->hasRole(Role::ADMIN->value)
            && $report->status->canTransitionTo(SowStatus::ARCHIVED);
    }

    /** Draft only; admin, manager, or owning supervisor. */
    public function attach(User $user, SowReport $report): bool
    {
        if ($report->status !== SowStatus::DRAFT) {
            return false;
        }

        if (! $this->access->inMarket($user, $report)) {
            return false;
        }

        return $this->access->canManageOrOwnsReport($user, $report);
    }

    /** Admin can delete any SOW; Manager can delete in-market SOWs (draft/in-review/published/archived); Field supervisor can only delete their own draft. */
    public function delete(User $user, SowReport $report): bool
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

        if ($user->hasRole(Role::MANAGER->value)) {
            return $this->access->inMarket($user, $report);
        }

        return $user->hasRole(Role::FIELD_SUPERVISOR->value)
            && $report->status === SowStatus::DRAFT
            && $report->created_by === $user->id
            && $this->access->inMarket($user, $report);
    }

    /** Admin or managing manager can unarchive an archived SOW report. */
    public function unarchive(User $user, SowReport $report): bool
    {
        if ($report->status !== SowStatus::ARCHIVED) {
            return false;
        }

        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

        return $user->hasRole(Role::MANAGER->value) && $this->access->inMarket($user, $report);
    }

    /**
     * Photo-level remove rule for the SOW strip (routes are unscoped, so a
     * cross-report image id is denied here too).
     * - Admin / manager: any photo on an unfinalized in-market report.
     * - Field supervisor: own photos (by library uploader) on draft reports.
     */
    public function deleteImage(User $user, SowReport $report, SowAnnotatedImage $image): bool
    {
        if ($image->sow_report_id !== $report->id || ! $this->access->inMarket($user, $report)) {
            return false;
        }

        if ($user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value])) {
            return $this->canManagerDeleteImage($report);
        }

        return $user->hasRole(Role::FIELD_SUPERVISOR->value)
            && $report->status === SowStatus::DRAFT
            && (int) $this->imageOwnerId($image) === $user->id;
    }

    /** Managers/admins cannot strip photos from a technician's untouched draft. */
    private function canManagerDeleteImage(SowReport $report): bool
    {
        if ($report->status === SowStatus::DRAFT && $this->access->isCreatedByFieldSupervisor($report)) {
            return false;
        }

        return $this->access->isUnfinalized($report);
    }

    private function imageOwnerId(SowAnnotatedImage $image): ?int
    {
        $ownerId = $image->relationLoaded('mediaAsset') && $image->mediaAsset !== null
            ? $image->mediaAsset->user_id
            : $image->mediaAsset()->value('user_id');

        return is_numeric($ownerId) ? (int) $ownerId : null;
    }

    public function download(User $user, SowReport $report): bool
    {
        return $this->view($user, $report);
    }

    /** Manager or Admin only; field supervisors and customers cannot regenerate. */
    public function regenerate(User $user, SowReport $report): bool
    {
        if (! $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value])) {
            return false;
        }

        return $this->access->inMarket($user, $report) && $report->status === SowStatus::PUBLISHED;
    }
}
