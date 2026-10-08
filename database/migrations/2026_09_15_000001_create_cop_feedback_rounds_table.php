<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cop_feedback_rounds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('closeout_package_id')->constrained('closeout_packages')->cascadeOnDelete();
            $table->unsignedSmallInteger('round_number')->default(1);
            // Lifecycle: open -> submitted -> resolved -> accepted.
            $table->string('status', 40)->default('open')->index();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One round per number per package; also guards the count()+1 race
            // when two managers open a round simultaneously.
            $table->unique(['closeout_package_id', 'round_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cop_feedback_rounds');
    }
};
