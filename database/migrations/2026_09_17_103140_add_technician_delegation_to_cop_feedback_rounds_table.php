<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cop_feedback_rounds', function (Blueprint $table) {
            $table->timestamp('assigned_to_technician_at')->nullable()->after('submitted_by');
            $table->timestamp('technician_submitted_at')->nullable()->after('assigned_to_technician_at');
            $table->foreignId('technician_submitted_by')
                ->nullable()
                ->after('technician_submitted_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cop_feedback_rounds', function (Blueprint $table) {
            $table->dropForeign(['technician_submitted_by']);
            $table->dropColumn([
                'assigned_to_technician_at',
                'technician_submitted_at',
                'technician_submitted_by',
            ]);
        });
    }
};
