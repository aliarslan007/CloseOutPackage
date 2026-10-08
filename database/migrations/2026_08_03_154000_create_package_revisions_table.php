<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('closeout_package_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('final');
            $table->unsignedTinyInteger('revision_major')->default(1);
            $table->unsignedTinyInteger('revision_minor')->default(0);
            $table->string('revision_label', 20);
            $table->date('revision_date')->nullable();
            $table->unsignedTinyInteger('total_pages')->default(0);
            $table->string('storage_path', 1000)->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->unsignedInteger('generation_duration_ms')->nullable();
            $table->string('renderer_version', 50)->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->string('emailed_to', 500)->nullable();
            $table->timestamps();

            $table->index('closeout_package_id');
            $table->index('type');
            $table->index('generated_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_revisions');
    }
};
