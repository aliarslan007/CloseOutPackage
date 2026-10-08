<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_site_info', function (Blueprint $table): void {
            $table->string('scope_of_work', 2500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('package_site_info', function (Blueprint $table): void {
            $table->string('scope_of_work', 1000)->nullable()->change();
        });
    }
};
