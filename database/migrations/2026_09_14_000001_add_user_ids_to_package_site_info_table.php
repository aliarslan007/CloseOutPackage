<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_site_info', function (Blueprint $table): void {
            $table->unsignedBigInteger('author_user_id')->nullable()->after('closeout_package_id');
            $table->unsignedBigInteger('pm_user_id')->nullable()->after('author_user_id');
        });

        if (DB::getDriverName() === 'mysql') {
            // Backfill author_user_id from the package creator — created_by IS the author.
            DB::statement('
                UPDATE package_site_info psi
                INNER JOIN closeout_packages cp ON cp.id = psi.closeout_package_id
                SET psi.author_user_id = cp.created_by
                WHERE cp.created_by IS NOT NULL
            ');

            // Backfill pm_user_id from the project manager — manager_id IS the PM.
            DB::statement('
                UPDATE package_site_info psi
                INNER JOIN closeout_packages cp ON cp.id = psi.closeout_package_id
                INNER JOIN projects p ON p.id = cp.project_id
                SET psi.pm_user_id = p.manager_id
                WHERE p.manager_id IS NOT NULL
            ');
        }
    }

    public function down(): void
    {
        Schema::table('package_site_info', function (Blueprint $table): void {
            $table->dropColumn(['author_user_id', 'pm_user_id']);
        });
    }
};
