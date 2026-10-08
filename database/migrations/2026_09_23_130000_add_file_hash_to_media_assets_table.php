<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            // SHA-256 of the stored bytes. Deduplicates device/camera photo
            // uploads against the library; null until backfilled for legacy
            // rows. Not unique: pre-existing duplicates must not block it.
            $table->string('file_hash', 64)->nullable()->after('validation_error');
            $table->index('file_hash');
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropIndex(['file_hash']);
            $table->dropColumn('file_hash');
        });
    }
};
