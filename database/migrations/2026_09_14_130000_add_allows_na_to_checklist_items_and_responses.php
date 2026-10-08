<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_items', function (Blueprint $table): void {
            $table->boolean('allows_na')->default(false)->after('has_explain_field');
        });

        Schema::table('package_checklist_responses', function (Blueprint $table): void {
            $table->boolean('is_na')->default(false)->after('answer');
        });
    }

    public function down(): void
    {
        Schema::table('package_checklist_responses', function (Blueprint $table): void {
            $table->dropColumn('is_na');
        });

        Schema::table('checklist_items', function (Blueprint $table): void {
            $table->dropColumn('allows_na');
        });
    }
};
