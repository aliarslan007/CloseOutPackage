<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sow_reports', function (Blueprint $table): void {
            $table->timestamp('reopened_at')->nullable()->after('published_by');
            $table->foreignId('reopened_by')->nullable()->after('reopened_at')
                ->constrained('users')->nullOnDelete();
            $table->text('reopen_notes')->nullable()->after('reopened_by');
        });
    }

    public function down(): void
    {
        Schema::table('sow_reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reopened_by');
            $table->dropColumn(['reopened_at', 'reopen_notes']);
        });
    }
};
