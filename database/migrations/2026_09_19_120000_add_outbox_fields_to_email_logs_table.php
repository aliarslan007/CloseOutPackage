<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turns email_logs into the Customer Emails outbox.
 *
 * A row is created as an *intent* (status "ready") and only becomes an actual
 * send once a manager releases it — now or on a schedule. The columns added
 * here are what the outbox needs to list, group, authorise and reschedule
 * those intents without a second table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_logs', function (Blueprint $table): void {
            // When the manager wants this delivered. Null = not scheduled.
            $table->timestamp('scheduled_at')->nullable();
            // The manager who released/cancelled it. Null when the intent was
            // recorded by a queued notification with no acting user.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Set when a scheduled (or pending) send was cancelled.
            $table->timestamp('cancelled_at')->nullable();
            // Change-order emails store their entity instead of a tokenized
            // payload: tokens are minted at the moment of delivery.
            $table->foreignId('change_order_id')->nullable()->constrained('change_orders')->nullOnDelete();
            // Mirrors SendEmailDTO::$contextId so intent rows can be grouped and
            // de-duplicated. Previously only present inside the idempotency hash.
            $table->string('context_id', 191)->nullable();
            // The serialized SendEmailDTO for types that cannot be rebuilt from
            // live relations by the time delivery runs.
            $table->json('payload')->nullable();

            // The outbox filters by status and surfaces overdue schedules.
            $table->index('status');
            $table->index('scheduled_at');
            $table->index('context_id');
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropIndex(['scheduled_at']);
            $table->dropIndex(['context_id']);

            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('change_order_id');

            $table->dropColumn([
                'scheduled_at',
                'cancelled_at',
                'context_id',
                'payload',
            ]);
        });
    }
};
