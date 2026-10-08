<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sow_annotated_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sow_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->json('annotation_layer');
            $table->string('composite_path')->nullable();
            $table->string('composite_status')->default('not_needed');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('page_title')->nullable();
            $table->boolean('is_cover')->default(false);
            $table->boolean('include_in_pdf')->default(true);
            $table->timestamp('annotations_updated_at', 3)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['sow_report_id', 'sort_order']);
            $table->index('composite_status');
            $table->index(['sow_report_id', 'include_in_pdf']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sow_annotated_images');
    }
};
