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
            $table->string('site_address', 500)->nullable()->after('address');
            $table->text('site_access_notes')->nullable()->after('site_address');
            $table->text('site_access_directions')->nullable()->after('site_access_notes');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn(['site_address', 'site_access_notes', 'site_access_directions']);
        });
    }
};
