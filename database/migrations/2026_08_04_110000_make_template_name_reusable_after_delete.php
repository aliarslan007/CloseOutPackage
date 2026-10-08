<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            $table->string('active_name')->storedAs('CASE WHEN deleted_at IS NULL THEN name ELSE NULL END');
            // add the replacement index first: MySQL rejects dropping the old one
            // while it is still the FK's supporting index (error 1553)
            $table->unique(['market_id', 'active_name'], 'templates_market_id_active_name_unique');
            $table->dropUnique('templates_market_id_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            $table->dropUnique('templates_market_id_active_name_unique');
            $table->unique(['market_id', 'name']);
            $table->dropColumn('active_name');
        });
    }
};
