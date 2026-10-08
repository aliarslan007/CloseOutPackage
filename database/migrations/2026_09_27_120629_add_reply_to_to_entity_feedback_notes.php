<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a note to the message it replies to, so a customer and a manager can hold
 * a conversation about one specific photo.
 *
 * Without it a second comment on the same photo is indistinguishable from a
 * comment on a different photo: every note is its own island. Grouping is
 * derived from source_photo_id, and this column records the explicit parent so
 * the thread order is unambiguous.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entity_feedback_notes', function (Blueprint $table): void {
            $table->foreignId('reply_to_note_id')->nullable()->after('id')
                ->constrained('entity_feedback_notes')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('entity_feedback_notes', function (Blueprint $table): void {
            $table->dropForeign(['reply_to_note_id']);
            $table->dropColumn('reply_to_note_id');
        });
    }
};
