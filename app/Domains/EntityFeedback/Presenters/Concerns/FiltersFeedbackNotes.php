<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Presenters\Concerns;

use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;

/**
 * Role-shaped trimming of the note list before it reaches a viewer's payload.
 *
 * These run on already-serialized note arrays (accessors and loaded relations
 * included), after the model's toArray and before the response goes out. Callers
 * must feed them chronologically - FeedbackRoundPresenter sorts the payload with
 * sortNotesChronologically() first, because the notes() relation carries no order.
 */
trait FiltersFeedbackNotes
{
    /**
     * A field supervisor who is not a manager or admin: they work an order, not
     * the relationship, so their payload is trimmed to that order below.
     */
    private function isTechnicianViewer(?User $viewer): bool
    {
        return $viewer !== null
            && $viewer->hasRole(Role::FIELD_SUPERVISOR->value)
            && ! $viewer->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]);
    }

    /**
     * The technician's work order only: the client's first message on each item
     * (it names the photo or section to fix) and the supervisor's own turns.
     * Manager-authored notes are the manager <-> customer conversation and are
     * deliberately hidden — the technician resolves the issue, not the dialogue.
     *
     * @param  array<int, array<string, mixed>>  $notes
     * @return array<int, array<string, mixed>>
     */
    private function notesVisibleToTechnician(array $notes, int $viewerId): array
    {
        $rootSeen = [];
        $visible = [];

        foreach ($notes as $note) {
            $key = $this->noteThreadKey($note);

            // The client's first message on this item — the work order.
            if (! isset($rootSeen[$key])) {
                $rootSeen[$key] = true;
                $visible[] = $note;

                continue;
            }

            // The supervisor's own turns, so they see what they already sent.
            if ((int) ($note['created_by'] ?? 0) === $viewerId) {
                $visible[] = $note;
            }

            // Everything else (manager replies to customer, customer follow-ups)
            // stays on the server — not the technician's concern.
        }

        return $visible;
    }

    /**
     * The item this note belongs to, mirroring the client's thread grouping: a
     * photo (by its marked-up type) when there is one, else the whole section.
     *
     * @param  array<string, mixed>  $note
     */
    private function noteThreadKey(array $note): string
    {
        $photoId = $note['source_photo_id'] ?? null;
        $type = $note['source_photo_type'] ?? null;

        if ($photoId === null) {
            return 'section:'.($type ?? 'none').':'.($note['feedback_round_id'] ?? '');
        }

        return ($type ?? 'photo').':'.$photoId;
    }

    /**
     * Role names carried by a note's author, flattened from the loaded relation.
     * Handles both relation-shaped (array of role arrays) and simple string roles.
     *
     * @param  array<string, mixed>  $note
     * @return array<int, string>
     */
    private function authorRoleNames(array $note): array
    {
        $roles = $note['author']['roles'] ?? [];

        if (! is_array($roles)) {
            return [];
        }

        $names = [];

        foreach ($roles as $role) {
            $name = is_array($role) ? ($role['name'] ?? null) : (is_string($role) ? $role : null);

            if ($name !== null) {
                $names[] = (string) $name;
            }
        }

        return $names;
    }

    /**
     * Oldest turn first, id as the same-instant tie-break. The client groups notes
     * into threads by first-seen order and the technician trim keeps the first
     * message per item - both need chronological input. The notes() relation carries
     * no default order (query callers depend on that), so the payload is sorted here.
     *
     * @param  array<int, array<string, mixed>>  $notes
     * @return array<int, array<string, mixed>>
     */
    private function sortNotesChronologically(array $notes): array
    {
        usort($notes, static fn (array $a, array $b): int => [
            $a['created_at'] ?? '',
            (int) ($a['id'] ?? 0),
        ] <=> [
            $b['created_at'] ?? '',
            (int) ($b['id'] ?? 0),
        ]);

        return $notes;
    }
}
