<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Actions;

use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use Illuminate\Support\Facades\DB;

/**
 * Removes a whole review round — the archive entry an admin deletes when a past
 * customer review should no longer exist.
 *
 * The note rows go with the database's ON DELETE CASCADE, but their media does
 * not: Spatie relates files by morph key, so a cascade would orphan both the
 * rows and the images on disk. Clearing each note's collections first is what
 * keeps "delete the review" from meaning "keep the customer's photos forever".
 */
final class DeleteFeedbackRoundAction
{
    public function execute(EntityFeedbackRound $round): void
    {
        DB::transaction(function () use ($round): void {
            $round->notes()->each(function (EntityFeedbackNote $note): void {
                $note->clearFeedbackMedia();
            });

            $round->delete();
        });
    }
}
