<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Actions;

use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\DB;

/**
 * Correcting your own message.
 *
 * Allowed for a short window after writing it — a typo should not be permanent.
 * The window is enforced here as well as in the policy, because this action is
 * also reachable from places that authorize differently, and the rule is about
 * the note, not about the route.
 */
final class UpdateFeedbackNoteAction
{
    public function execute(EntityFeedbackNote $note, int $actorId, string $body): EntityFeedbackNote
    {
        if (! $note->messageEditableBy($actorId)) {
            throw new InvalidStateTransitionException(
                $this->refusal($note, $actorId)
            );
        }

        return DB::transaction(function () use ($note, $body): EntityFeedbackNote {
            $note->update([
                'body' => $body,
                'edited_at' => now(),
            ]);

            return $note;
        });
    }

    /** The reason matters: "someone else wrote this" reads very differently from "too late". */
    private function refusal(EntityFeedbackNote $note, int $actorId): string
    {
        if ($note->created_by !== $actorId) {
            return 'Only the author can edit a message.';
        }

        if ($note->isWithdrawn()) {
            return 'This issue was withdrawn, so its messages can no longer be changed.';
        }

        return 'Messages can only be edited for '.EntityFeedbackNote::editWindowMinutes()
            .' minutes after they are written.';
    }
}
