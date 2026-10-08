<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Close-out packages carry section-level notes that are not about a specific
 * photo, so a feedback note no longer requires an image to annotate. The
 * annotation columns stay nullable, which they already were.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entity_feedback_notes', function (Blueprint $table): void {
            $table->string('original_image_url', 1000)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('entity_feedback_notes', function (Blueprint $table): void {
            $table->string('original_image_url', 1000)->nullable(false)->change();
        });
    }
};
