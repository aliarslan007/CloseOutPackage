<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entity_feedback_notes', function (Blueprint $table): void {
            $table->string('requested_action', 50)->nullable()->after('source_photo_type');
        });
    }

    public function down(): void
    {
        Schema::table('entity_feedback_notes', function (Blueprint $table): void {
            $table->dropColumn('requested_action');
        });
    }
};
