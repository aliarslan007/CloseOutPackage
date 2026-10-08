<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->string('type', 50);
            $table->text('description');
            $table->text('verizon_guidance')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('project_id');
            $table->index('type');
            $table->index('reported_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_histories');
    }
};
