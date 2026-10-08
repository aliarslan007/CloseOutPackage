<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issue_histories', function (Blueprint $table): void {
            $table->foreignId('resolved_by')
                ->nullable()
                ->after('reported_by')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('resolved_by');
        });
    }

    public function down(): void
    {
        Schema::table('issue_histories', function (Blueprint $table): void {
            $table->dropForeign(['resolved_by']);
            $table->dropColumn('resolved_by');
        });
    }
};
