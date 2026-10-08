<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_history_id')->constrained('issue_histories')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index('issue_history_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_comments');
    }
};
