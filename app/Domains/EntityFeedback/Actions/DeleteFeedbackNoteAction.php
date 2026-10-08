<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Actions;

use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\DB;

final class DeleteFeedbackNoteAction
{
    public function execute(EntityFeedbackNote $note): void
    {
        if (! $note->round->isOpen()) {
            throw new InvalidStateTransitionException(
                'Feedback notes can only be deleted while the round is open in draft.'
            );
        }

        DB::transaction(function () use ($note): void {
            $note->clearFeedbackMedia();
            $note->delete();
        });
    }
}
