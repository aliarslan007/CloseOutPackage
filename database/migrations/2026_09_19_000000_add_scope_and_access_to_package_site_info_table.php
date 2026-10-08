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
            // Captured per package on the pre-construction step, not per project.
            $table->string('scope_of_work', 350)->nullable()->after('complete_date');
            $table->text('site_access_notes')->nullable()->after('scope_of_work');
        });
    }

    public function down(): void
    {
        Schema::table('package_site_info', function (Blueprint $table): void {
            $table->dropColumn(['scope_of_work', 'site_access_notes']);
        });
    }
};
