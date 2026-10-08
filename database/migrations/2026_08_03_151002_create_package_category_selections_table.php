<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_category_selections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_picture_category_id')->constrained()->restrictOnDelete();
            $table->boolean('is_selected')->default(true);
            $table->unsignedTinyInteger('picture_count')->default(1);
            $table->string('user_heading', 500)->nullable();
            $table->text('user_notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['package_stage_id', 'template_picture_category_id'],
                'package_category_selections_stage_category_unique',
            );
            $table->index('template_picture_category_id', 'package_category_selections_category_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_category_selections');
    }
};
