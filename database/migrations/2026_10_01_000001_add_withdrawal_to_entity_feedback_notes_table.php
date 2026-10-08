<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer's "I don't want this one fixed" mark, stored per message so the
 * withdrawal covers exactly the conversation the customer was reading. The round
 * and every other issue in it stay live, and nothing is erased: the message and
 * its history remain readable, labelled as withdrawn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entity_feedback_notes', function (Blueprint $table): void {
            $table->timestamp('withdrawn_at')->nullable()->after('response_sent_at');
            $table->foreignId('withdrawn_by')->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('withdrawn_at');
        });
    }

    public function down(): void
    {
        Schema::table('entity_feedback_notes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('withdrawn_by');
            $table->dropColumn('withdrawn_at');
        });
    }
};
