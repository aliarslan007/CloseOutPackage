<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 50);
            $table->string('heading', 500);
            $table->text('admin_notes')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['template_id', 'stage']);
            $table->index('stage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_sections');
    }
};
