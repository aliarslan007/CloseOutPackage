<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            // Clear lifecycle: draft -> sent_to_customer -> (customer_action_required |
            // partially_approved | approved | rejected). customer_action_required is
            // set when the send-time deadline passes without full decisions.
            $table->string('status', 50)->default('draft')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('decision_deadline_at')->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_orders');
    }
};
