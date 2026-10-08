<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('closeout_packages', function (Blueprint $table): void {
            $table->timestamp('locked_at')->nullable()->after('approved_by');
            $table->foreignId('locked_by')->nullable()->after('locked_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('closeout_packages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('locked_by');
            $table->dropColumn('locked_at');
        });
    }
};
