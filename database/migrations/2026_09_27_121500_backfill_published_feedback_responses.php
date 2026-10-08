<?php

use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfills response_sent_at for answers the manager published before that column
 * existed.
 *
 * The column answers "has the customer been sent this answer?", and every record
 * predating it carries the default of "no" — which silently hid answers the
 * customer had already been sent. The manager saw "Answered"; the customer saw
 * nothing.
 *
 * Scoped to notes whose round is already resolved or accepted, because a note
 * resolved while the round is still submitted is an answer the manager is still
 * working on, and that one must stay private.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sentStatuses = [
            FeedbackRoundStatus::RESOLVED->value,
            FeedbackRoundStatus::ACCEPTED->value,
        ];

        $affected = DB::table('entity_feedback_notes')
            ->whereNotNull('resolved_at')
            ->whereNull('response_sent_at')
            ->whereIn('feedback_round_id', fn ($query) => $query
                ->select('id')
                ->from('entity_feedback_rounds')
                ->whereIn('status', $sentStatuses))
            // The answer shipped the moment it was resolved.
            ->update(['response_sent_at' => DB::raw('resolved_at')]);

        if ($affected > 0) {
            info("Backfilled response_sent_at on {$affected} published feedback note(s).");
        }
    }

    public function down(): void
    {
        // Nothing to undo: the values are derived from resolved_at, and the
        // column itself is dropped by the migration that introduced it.
    }
};
