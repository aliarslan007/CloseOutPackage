<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('closeout_package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_email', 255);
            $table->string('recipient_name', 255)->nullable();
            $table->string('sender_email', 255);
            $table->string('sender_name', 255)->nullable();
            $table->json('cc_emails')->nullable();
            $table->string('subject', 1000);
            $table->text('body_preview')->nullable();
            $table->string('type', 80);
            $table->string('status', 40)->default('sent');
            $table->timestamp('sent_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->string('provider_message_id', 255)->nullable();
            $table->timestamps();

            $table->index('closeout_package_id');
            $table->index('project_id');
            $table->index('recipient_email');
            $table->index('type');
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
