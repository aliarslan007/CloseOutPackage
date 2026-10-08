<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_picture_categories', function (Blueprint $table): void {
            $table->softDeletes()->after('sort_order');
        });

        Schema::table('checklist_items', function (Blueprint $table): void {
            $table->softDeletes()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('template_picture_categories', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('checklist_items', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
