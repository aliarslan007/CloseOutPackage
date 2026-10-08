<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cop_feedback_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('feedback_round_id')->constrained('cop_feedback_rounds')->cascadeOnDelete();
            $table->foreignId('closeout_package_id')->constrained('closeout_packages')->cascadeOnDelete();
            // Explicit enum column (photo|category|package) instead of Eloquent
            // polymorphism — keeps queries predictable; package-level notes have no target_id.
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->text('body');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('manager_response')->nullable();
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cop_feedback_notes');
    }
};
