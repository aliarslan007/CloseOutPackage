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
            // Tracks which library preset a category was pulled from, so the same
            // preset cannot be pulled twice into one stage. The values are copied,
            // so deleting the preset never touches the template (nullOnDelete).
            $table->foreignId('category_preset_id')
                ->nullable()
                ->after('template_section_id')
                ->constrained('category_presets')
                ->nullOnDelete();

            $table->unique(
                ['template_section_id', 'category_preset_id'],
                'template_picture_categories_section_preset_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('template_picture_categories', function (Blueprint $table): void {
            $table->dropUnique('template_picture_categories_section_preset_unique');
            $table->dropConstrainedForeignId('category_preset_id');
        });
    }
};
