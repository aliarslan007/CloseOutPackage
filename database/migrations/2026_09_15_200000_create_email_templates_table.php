<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table): void {
            $table->id();
            // String value of App\Domains\Email\Enums\EmailType; validated at
            // the app layer — no hard FK so enum cases can evolve freely.
            $table->string('email_type', 50);
            $table->string('name');
            $table->string('subject_template', 500);
            $table->text('body_template');
            // Lifecycle: draft -> active -> archived. Only active templates
            // appear in the send-flow picker.
            $table->string('status', 20)->default('active');
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Picker + "default for type" lookups (leftmost prefix also serves
            // plain email_type filters). One-default-per-type is enforced
            // transactionally in SetDefaultEmailTemplateAction — a unique key
            // on (email_type, is_default) would wrongly limit each type to a
            // single non-default template as well.
            $table->index(['email_type', 'status', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
