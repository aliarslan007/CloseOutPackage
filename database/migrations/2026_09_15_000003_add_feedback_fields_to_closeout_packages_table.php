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
            $table->timestamp('customer_review_opened_at')->nullable()->after('locked_by');
            $table->foreignId('customer_review_opened_by')->nullable()->after('customer_review_opened_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('closeout_packages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_review_opened_by');
            $table->dropColumn('customer_review_opened_at');
        });
    }
};
