<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the author corrected their own message.
 *
 * A typo in a query or an answer should not cost the author their words, so a
 * note's body becomes editable. Rewriting it silently would be worse than not
 * editing at all — the reader could no longer trust what they read a minute
 * ago — so the edit is stamped and shown next to the message.
 *
 * A manager's *answer* is tracked separately by response_sent_at (see the
 * acknowledgement migration): a draft answer is editable until it is sent, a
 * published one is not editable at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entity_feedback_notes', function (Blueprint $table): void {
            $table->timestamp('edited_at')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('entity_feedback_notes', function (Blueprint $table): void {
            $table->dropColumn('edited_at');
        });
    }
};
