<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two pieces of state the feedback loop was missing:
 *
 *  1. The manager acknowledging a customer's submission, so the customer can see
 *     it was received and work has started rather than it vanishing into an
 *     inbox.
 *  2. When a manager's answer was actually published. A per-note reply is a
 *     draft until "Send Resolution to Customer" is pressed, so visibility is
 *     stamped on the note rather than inferred from the round's status — that
 *     way a published answer stays readable even after the customer comes back
 *     with a follow-up and the round returns to in-progress.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entity_feedback_rounds', function (Blueprint $table): void {
            $table->timestamp('manager_acknowledged_at')->nullable()->after('submitted_by');
            $table->foreignId('manager_acknowledged_by')->nullable()
                ->constrained('users')->nullOnDelete()
                ->after('manager_acknowledged_at');
        });

        Schema::table('entity_feedback_notes', function (Blueprint $table): void {
            $table->timestamp('response_sent_at')->nullable()->after('resolved_by');
        });
    }

    public function down(): void
    {
        Schema::table('entity_feedback_notes', function (Blueprint $table): void {
            $table->dropColumn('response_sent_at');
        });

        Schema::table('entity_feedback_rounds', function (Blueprint $table): void {
            $table->dropForeign(['manager_acknowledged_by']);
            $table->dropColumn(['manager_acknowledged_at', 'manager_acknowledged_by']);
        });
    }
};
