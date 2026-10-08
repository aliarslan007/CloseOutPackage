<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->foreignId('source_asset_id')
                ->nullable()
                ->after('project_id')
                ->constrained('media_assets')
                ->nullOnDelete();
            $table->json('annotation_layer')
                ->nullable()
                ->after('validation_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropForeign(['source_asset_id']);
            $table->dropColumn(['source_asset_id', 'annotation_layer']);
        });
    }
};
