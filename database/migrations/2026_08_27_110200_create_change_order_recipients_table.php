<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_order_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('change_order_id')->constrained('change_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // SHA-256 hash of the emailed token — raw token never stored.
            // Null until the change order is sent (Phase 2).
            $table->string('token_hash', 64)->nullable()->unique();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['change_order_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_order_recipients');
    }
};
