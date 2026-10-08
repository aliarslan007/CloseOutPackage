<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_checklist_responses', function (Blueprint $table): void {
            $table->text('site_notes')->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('package_checklist_responses', function (Blueprint $table): void {
            $table->dropColumn('site_notes');
        });
    }
};
