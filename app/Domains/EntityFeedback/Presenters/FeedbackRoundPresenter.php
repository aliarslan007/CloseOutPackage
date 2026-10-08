<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Presenters;

use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\EntityFeedback\Policies\EntityFeedbackPolicy;
use App\Domains\EntityFeedback\Presenters\Concerns\FiltersFeedbackNotes;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

/**
 * Shapes a feedback round for the client.
 *
 * The point of this class is subtraction. A round the customer has not submitted
 * yet is their private draft, so for staff we ship the lifecycle (status, round
 * number, timestamps) and nothing else: no note bodies, no images, no ids, no
 * author names. Withholding it in the query layer rather than in a React
 * conditional is what makes "the manager cannot see what the customer is
 * writing" a property of the system instead of a promise about the UI.
 */
final class FeedbackRoundPresenter
{
    use FiltersFeedbackNotes;

    public const FLAG_NOTES_WITHHELD = 'notes_withheld';

    public function __construct(
        private readonly EntityFeedbackPolicy $policy,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function present(?EntityFeedbackRound $round, ?User $viewer): ?array
    {
        if ($round === null) {
            return null;
        }

        // A null viewer is the emailed-token path. The token is the credential,
        // so the round behind it is readable in full.
        $canRead = $viewer === null || $this->policy->viewNotes($viewer, $round);

        // A field supervisor who cannot view notes on this round has no business with it at all.
        if (! $canRead && $viewer !== null && $viewer->hasRole(Role::FIELD_SUPERVISOR->value) && ! $viewer->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value])) {
            return null;
        }

        if ($canRead) {
            // Each note carries its author and the person who answered it — name,
            // avatar, and role — so the conversation can attribute each message
            // and reply to a real human.
            $round->loadMissing([
                'notes.resolvedByUser.roles',
                'notes.author.roles',
            ]);
        }

        /** @var array<string, mixed> $payload */
        $payload = $round->toArray();

        // Whether the viewer may erase this whole round. Decided here, not by a
        // role check in React, so the trash icon can never outlive the policy.
        $payload['can_delete_round'] = $viewer !== null && $this->policy->deleteRound($viewer, $round);

        if ($canRead) {
            unset($payload[self::FLAG_NOTES_WITHHELD]);

            /** @var array<int, array<string, mixed>> $notes */
            $notes = $this->sortNotesChronologically(
                is_array($payload['notes'] ?? null) ? $payload['notes'] : []
            );

            // A field supervisor works an order, not the relationship: only the
            // client's first ask, the manager's notes and their own replies reach
            // them. Manager/admin staff see the whole thread; the customer still
            // gets unsent drafts withheld.
            if ($this->isTechnicianViewer($viewer)) {
                $notes = $this->notesVisibleToTechnician($notes, $viewer->id);
            } elseif (! $this->isStaff($viewer)) {
                $notes = $this->withoutUnsentResponses($notes);
            }

            $payload['notes'] = $this->withEditPermissions($notes, $round, $viewer);

            return $payload;
        }

        $payload['notes'] = [];
        $payload[self::FLAG_NOTES_WITHHELD] = true;

        // Anything that would leak the draft's shape or its author.
        foreach (['submitted_at', 'submitted_by', 'assigned_to_user_id', 'assigned_at', 'assigned_by', 'resolved_at', 'resolved_by', 'accepted_at', 'accepted_by'] as $key) {
            unset($payload[$key]);
        }

        return $payload;
    }

    /**
     * Whether each note can still be corrected, decided by the server.
     *
     * The client only shows or hides the menu item — it never re-derives the
     * edit window itself, so a tab left open past the window cannot offer an
     * edit the server will refuse.
     *
     * @param  array<int, array<string, mixed>>  $notes
     * @return array<int, array<string, mixed>>
     */
    private function withEditPermissions(array $notes, EntityFeedbackRound $round, ?User $viewer): array
    {
        $viewerId = $viewer?->id;

        $permissions = $round->notes->mapWithKeys(fn (EntityFeedbackNote $note): array => [
            $note->id => [
                'can_edit' => $note->messageEditableBy($viewerId),
                'can_edit_answer' => $note->answerEditableBy($viewerId),
            ],
        ])->all();

        return array_map(function (array $note) use ($permissions): array {
            $id = $this->noteId($note);

            if ($id === null || ! isset($permissions[$id])) {
                return $note + ['can_edit' => false, 'can_edit_answer' => false];
            }

            return $note + $permissions[$id];
        }, $notes);
    }

    /** @param array<string, mixed> $note */
    private function noteId(array $note): ?int
    {
        $id = $note['id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    private function isStaff(?User $viewer): bool
    {
        return $viewer !== null && $viewer->hasAnyRole([
            Role::ADMIN->value,
            Role::MANAGER->value,
            Role::FIELD_SUPERVISOR->value,
        ]);
    }

    /**
     * The customer's view of a note the manager has not published yet: the
     * question only. Whether a note is answered is the manager's progress, not
     * the customer's, until they press "Send Resolution to Customer".
     *
     * Keyed on response_sent_at rather than the round status, so an answer that
     * was already sent stays readable when the customer comes back with a
     * follow-up and the round returns to in-progress.
     *
     * @param  array<int, array<string, mixed>>  $notes
     * @return array<int, array<string, mixed>>
     */
    private function withoutUnsentResponses(array $notes): array
    {
        return array_map(function (array $note): array {
            if (($note['response_sent_at'] ?? null) !== null) {
                return $note;
            }

            $staffRoles = [Role::ADMIN->value, Role::MANAGER->value, Role::FIELD_SUPERVISOR->value];
            if (array_intersect($this->authorRoleNames($note), $staffRoles) !== []) {
                return $note;
            }

            unset(
                $note['manager_response'],
                $note['resolved_at'],
                $note['resolved_by'],
                $note['resolved_by_user'],
                $note['replacement_media_id'],
                $note['replacement_image_url'],
            );

            // An accessor value, so it has to be reset rather than unset.
            $note['is_resolved'] = false;

            return $note;
        }, $notes);
    }

    /**
     * Every round this entity has been through, oldest first.
     *
     * Once a change order is approved there is no active round, and without this
     * the customer loses the record of what was discussed on each photo. Read
     * only: the lifecycle is unchanged, this only makes the history reachable.
     *
     * @param  iterable<EntityFeedbackRound>  $rounds
     * @return list<array<string, mixed>>
     */
    public function presentAll(iterable $rounds, ?User $viewer): array
    {
        $payload = [];

        foreach ($rounds as $round) {
            $presented = $this->present($round, $viewer);

            if ($presented !== null) {
                $payload[] = $presented;
            }
        }

        return $payload;
    }

    /**
     * Attaches the presented round under a stable key and drops the raw relation
     * so the same data is never serialized twice under two names.
     *
     * @param  Arrayable&Model  $model
     * @return Arrayable&Model
     */
    public function attachTo(object $model, string $key, ?EntityFeedbackRound $round, ?User $viewer): object
    {
        $payload = $this->present($round, $viewer);

        $model->setAttribute($key, $payload);

        foreach (['activeFeedbackRound', 'openFeedbackRound'] as $relation) {
            if ($model->relationLoaded($relation)) {
                $model->unsetRelation($relation);
            }
        }

        return $model;
    }
}
