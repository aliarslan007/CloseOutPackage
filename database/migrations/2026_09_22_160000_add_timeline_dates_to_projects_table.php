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
            // Project timeline: the milestones tracked from award through
            // close-out. All nullable so pre-existing projects are unaffected;
            // the earliest three are enforced as required in the create form.
            $table->date('awarded_date')->nullable()->after('completed_date');
            $table->date('rec_issued_date')->nullable()->after('awarded_date');
            $table->date('construction_started_date')->nullable()->after('rec_issued_date');
            $table->date('site_completed_date')->nullable()->after('construction_started_date');
            // Auto-stamped when a manager/admin approves the close-out package.
            $table->date('closeout_package_date')->nullable()->after('site_completed_date');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn([
                'awarded_date',
                'rec_issued_date',
                'construction_started_date',
                'site_completed_date',
                'closeout_package_date',
            ]);
        });
    }
};
