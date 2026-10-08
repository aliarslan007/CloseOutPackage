<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sow_checklist_items', function (Blueprint $table): void {
            $table->foreignId('sow_report_id')->nullable()->after('id')->constrained('sow_reports')->cascadeOnDelete();
            $table->index(['sow_report_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('sow_checklist_items', function (Blueprint $table): void {
            $table->dropForeign(['sow_report_id']);
            $table->dropIndex(['sow_report_id', 'sort_order']);
            $table->dropColumn('sow_report_id');
        });
    }
};
