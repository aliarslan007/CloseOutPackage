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
            $table->text('manager_instructions')->nullable()->after('technician_submitted_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cop_feedback_rounds', function (Blueprint $table) {
            $table->dropColumn('manager_instructions');
        });
    }
};
