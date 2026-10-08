<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_picture_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_section_id')->constrained()->cascadeOnDelete();
            $table->string('name', 500);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('default_picture_count')->default(1);
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_picture_categories');
    }
};
