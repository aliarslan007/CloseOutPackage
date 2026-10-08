<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_pings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->string('status')->default('active');
            $table->timestamp('pinged_at');
            $table->timestamps();

            // One row per (project, user) — updateOrCreate keeps latest location.
            $table->unique(['project_id', 'user_id']);
            $table->index(['project_id', 'pinged_at']);
            $table->index('pinged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_pings');
    }
};
