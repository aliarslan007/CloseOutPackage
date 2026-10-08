<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('photo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->string('decision', 40);
            $table->string('reason', 1000)->nullable();
            $table->timestamps();

            $table->unique('photo_id', 'photo_approvals_photo_id_unique');
            $table->index('reviewed_by');
            $table->index('decision');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_approvals');
    }
};
