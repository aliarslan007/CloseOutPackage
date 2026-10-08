<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Policies;

use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\EntityFeedback\Services\FeedbackEntityResolver;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;

/**
 * Abilities for the single feedback engine.
 *
 * Two rules shape everything here:
 *
 *  1. Staff abilities are role-gated (admin/manager, plus the assigned
 *     technician where delegation applies).
 *  2. A customer ability is never "has the customer role" — it is "has been
 *     granted access to the project behind this round". Without the scope
 *     check, any logged-in customer could open, submit or accept a round
 *     belonging to somebody else's project by guessing an id.
 */
class EntityFeedbackPolicy
{
    public function __construct(
        private readonly FeedbackEntityResolver $entities,
    ) {}

    public function open(User $user): bool
    {
        return $user->hasAnyRole([Role::CUSTOMER->value, Role::ADMIN->value, Role::MANAGER->value]);
    }

    /**
     * Whether a viewer may see what the customer has written on this round.
     *
     * A round the customer is still writing (status OPEN) is their private draft.
     * Staff must not see it until it is submitted: the annotations, the note
     * bodies and the images are all withheld, so nobody can watch a customer
     * compose their review. Once submitted, staff may read the whole thread.
     * The author-customer always sees their own round, draft included.
     */
    public function viewNotes(User $user, EntityFeedbackRound $round): bool
    {
        if ($user->hasRole(Role::CUSTOMER->value) && $this->entities->customerCanAccess($user, $round)) {
            return true;
        }

        if ($round->isOpen()) {
            return false;
        }

        if ($user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value])) {
            return true;
        }

        if ($this->technicianCanAccessRound($user, $round)) {
            return true;
        }

        return false;
    }

    /**
     * Reading a single note follows the same rule as reading the round, so the
     * image route can never become a way around a withheld draft.
     */
    public function view(User $user, EntityFeedbackNote $note): bool
    {
        return $this->viewNotes($user, $note->round);
    }

    public function addNote(User $user, EntityFeedbackRound $round): bool
    {
        // The customer has ruled on the change order, so the review is over.
        if ($this->entities->entityIsDecided($round)) {
            return false;
        }

        // Active round states: open draft, submitted review discussion, or resolved follow-up.
        if (! $round->isOpen() && ! $round->isSubmitted() && ! $round->isResolved()) {
            return false;
        }

        if ($user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value])) {
            return true;
        }

        // A field supervisor resolves via Submit to Manager (round-level), not
        // by creating thread messages — the conversation stays customer <-> manager.

        return $user->hasRole(Role::CUSTOMER->value)
            && $this->entities->customerCanAccess($user, $round);
    }

    public function deleteNote(User $user, EntityFeedbackNote $note): bool
    {
        if (! $note->round->isOpen()) {
            return false;
        }

        if ($user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value])) {
            return true;
        }

        // The author, or a customer who has access to the same project.
        return $note->created_by === $user->id
            || ($user->hasRole(Role::CUSTOMER->value)
                && $this->entities->customerCanAccess($user, $note->round));
    }

    /**
     * Withdrawing one issue is the customer's call alone: they have decided a
     * problem is not worth fixing. Nobody else gets to drop a client's ask off
     * the list - staff resolve it or leave it open. Allowed whenever the review
     * can still move, which is exactly when a note can be added to it.
     */
    public function withdrawIssue(User $user, EntityFeedbackRound $round): bool
    {
        if ($this->entities->entityIsDecided($round) || $round->isAccepted()) {
            return false;
        }

        return $user->hasRole(Role::CUSTOMER->value)
            && $this->entities->customerCanAccess($user, $round);
    }

    /**
     * Deleting a past review erases the round, every message in it and the
     * images they carry. Admin only: a manager who disagrees with an archived
     * review answers it, they do not delete the customer's record of it.
     */
    public function deleteRound(User $user, EntityFeedbackRound $round): bool
    {
        return $user->hasRole(Role::ADMIN->value);
    }

    /**
     * Editing a message is the author's alone — not staff editing a customer's
     * words, and not a customer rewriting a query somebody else wrote. Whether a
     * withdrawn conversation is frozen lives on the note, so the policy, the
     * action and the client's edit flag can never drift apart.
     */
    public function updateNote(User $user, EntityFeedbackNote $note): bool
    {
        return $note->messageEditableBy($user->id);
    }

    public function submit(User $user, EntityFeedbackRound $round): bool
    {
        if ($this->entities->entityIsDecided($round) || ! $round->isOpen()) {
            return false;
        }

        if ($user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]) || $round->opened_by === $user->id) {
            return true;
        }

        return $user->hasRole(Role::CUSTOMER->value)
            && $this->entities->customerCanAccess($user, $round);
    }

    public function forwardTechnician(User $user, EntityFeedbackRound $round): bool
    {
        // A change order is raised and handled by a manager; the technician
        // delegation workflow does not exist for it, so the button and the
        // endpoint both refuse.
        if ($round->entity_type === FeedbackEntityType::CHANGE_ORDER) {
            return false;
        }

        return $round->isSubmitted() && $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]);
    }

    /**
     * Confirming receipt is a promise to the customer, so it is scoped to the
     * manager who actually owns the project rather than any manager.
     */
    public function acknowledge(User $user, EntityFeedbackRound $round): bool
    {
        if (! $round->isSubmitted() || $round->isAcknowledged()) {
            return false;
        }

        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

        if (! $user->hasRole(Role::MANAGER->value)) {
            return false;
        }

        return $this->entities->managerFor($round)?->id === $user->id;
    }

    public function submitTechnician(User $user, EntityFeedbackRound $round): bool
    {
        if (! $round->isSubmitted() || $round->technician_submitted_at !== null) {
            return false;
        }

        if ($user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value])) {
            return true;
        }

        if ($this->technicianCanAccessRound($user, $round)) {
            return $round->assigned_at !== null || $round->assigned_to_user_id !== null;
        }

        return false;
    }

    public function resolveNote(User $user, EntityFeedbackNote $note): bool
    {
        // A withdrawn issue is off the list; nobody resolves what the customer
        // has asked to be dropped.
        if ($note->isWithdrawn()) {
            return false;
        }

        if ($user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value])) {
            return true;
        }

        return $this->technicianCanAccessRound($user, $note->round);
    }

    public function resolveAll(User $user, EntityFeedbackRound $round): bool
    {
        return $round->isSubmitted() && $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]);
    }

    public function accept(User $user, EntityFeedbackRound $round): bool
    {
        if ($round->isAccepted()) {
            return false;
        }

        // Acceptance closes the review loop, so it must be something the
        // manager has actually seen: submitted by the customer/technician, or
        // already resolved. Accepting an OPEN draft would skip the review.
        if (! $round->isSubmitted() && ! $round->isResolved()) {
            return false;
        }

        if ($user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]) || $round->submitted_by === $user->id) {
            return true;
        }

        return $user->hasRole(Role::CUSTOMER->value)
            && $this->entities->customerCanAccess($user, $round);
    }

    private function technicianCanAccessRound(User $user, EntityFeedbackRound $round): bool
    {
        if (! $user->hasRole(Role::FIELD_SUPERVISOR->value)) {
            return false;
        }

        if ($round->assigned_to_user_id !== null) {
            return $round->assigned_to_user_id === $user->id;
        }

        if ($round->assigned_at !== null) {
            $project = $this->entities->projectFor($round);

            return $project !== null && $project->fieldSupervisors()->where('users.id', $user->id)->exists();
        }

        return false;
    }
}
