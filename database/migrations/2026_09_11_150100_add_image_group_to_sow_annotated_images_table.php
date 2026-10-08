<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sow_annotated_images', function (Blueprint $table): void {
            $table->foreignId('sow_image_group_id')->nullable()->after('media_asset_id')
                ->constrained('sow_image_groups')->nullOnDelete();

            $table->index(['sow_report_id', 'sow_image_group_id'], 'sow_images_report_group_index');
        });
    }

    public function down(): void
    {
        Schema::table('sow_annotated_images', function (Blueprint $table): void {
            $table->dropIndex('sow_images_report_group_index');
            $table->dropConstrainedForeignId('sow_image_group_id');
        });
    }
};
