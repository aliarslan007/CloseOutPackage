<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('closeout_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('draft');
            $table->unsignedTinyInteger('revision_major')->default(1);
            $table->unsignedTinyInteger('revision_minor')->default(0);
            $table->date('revision_date')->nullable();
            $table->string('current_stage', 50)->default('pre_construction');
            $table->text('notes')->nullable();
            $table->string('final_pdf_path', 1000)->nullable();
            $table->timestamp('final_pdf_generated_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('current_stage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closeout_packages');
    }
};
