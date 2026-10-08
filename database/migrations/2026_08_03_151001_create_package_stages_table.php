<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_stages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('closeout_package_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 50);
            $table->string('status', 40)->default('not_started');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('draft_saved_at')->nullable();
            $table->timestamps();

            $table->unique(['closeout_package_id', 'stage']);
            $table->index('stage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_stages');
    }
};
