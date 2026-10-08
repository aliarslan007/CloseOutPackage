<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            // The existing market_id column IS the project's region.
            $table->foreignId('customer_id')->nullable()->after('market_id')->constrained('customers')->nullOnDelete();
            $table->foreignId('customer_engineer_id')->nullable()->after('customer_id')->constrained('customer_engineers')->nullOnDelete();

            $table->index('customer_id');
            $table->index('customer_engineer_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['customer_engineer_id']);
            $table->dropIndex(['customer_id']);
            $table->dropIndex(['customer_engineer_id']);
            $table->dropColumn(['customer_id', 'customer_engineer_id']);
        });
    }
};
