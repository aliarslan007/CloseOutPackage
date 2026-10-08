<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_feedback_rounds', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id');
            $table->unsignedSmallInteger('round_number')->default(1);
            $table->string('status', 30)->default('open');
            $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();

            // Technician / Field Supervisor Delegation Workflow
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('technician_submitted_at')->nullable();
            $table->foreignId('technician_submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('manager_instructions')->nullable();

            // Manager & Customer Final Resolution
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id', 'status', 'round_number'], 'entity_feedback_rounds_lookup_idx');
        });

        Schema::create('entity_feedback_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('feedback_round_id')->constrained('entity_feedback_rounds')->cascadeOnDelete();
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('source_photo_id')->nullable();
            $table->string('source_photo_type', 100)->nullable();
            $table->string('original_image_url', 1000);
            $table->foreignId('annotated_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->json('annotation_layer')->nullable();
            $table->text('body');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            // Technician / Manager response notes
            $table->text('manager_response')->nullable();
            $table->foreignId('replacement_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['feedback_round_id'], 'entity_feedback_notes_round_idx');
            $table->index(['entity_type', 'entity_id'], 'entity_feedback_notes_entity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_feedback_notes');
        Schema::dropIfExists('entity_feedback_rounds');
    }
};
