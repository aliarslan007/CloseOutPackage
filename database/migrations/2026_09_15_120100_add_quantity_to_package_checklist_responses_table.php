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
            $table->unsignedSmallInteger('quantity')->nullable()->after('is_na');
        });
    }

    public function down(): void
    {
        Schema::table('package_checklist_responses', function (Blueprint $table): void {
            $table->dropColumn('quantity');
        });
    }
};
