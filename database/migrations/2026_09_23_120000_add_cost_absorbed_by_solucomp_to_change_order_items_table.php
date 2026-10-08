<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('change_order_items', function (Blueprint $table): void {
            $table->boolean('cost_absorbed_by_solucomp')->default(false)->after('is_included');
        });

        Schema::table('change_orders', function (Blueprint $table): void {
            $table->date('change_order_date')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('change_orders', function (Blueprint $table): void {
            $table->dropColumn('change_order_date');
        });

        Schema::table('change_order_items', function (Blueprint $table): void {
            $table->dropColumn('cost_absorbed_by_solucomp');
        });
    }
};
