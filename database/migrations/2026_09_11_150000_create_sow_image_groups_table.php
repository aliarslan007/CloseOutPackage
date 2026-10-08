<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sow_image_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sow_report_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            // Two groups sharing a name would produce two identical PDF headings.
            // No soft deletes: a trashed row would keep occupying this index.
            $table->unique(['sow_report_id', 'name']);
            $table->index(['sow_report_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sow_image_groups');
    }
};
