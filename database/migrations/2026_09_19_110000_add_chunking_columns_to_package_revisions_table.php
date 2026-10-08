<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_revisions', function (Blueprint $table): void {
            // Chunked pipeline lifecycle. Default 'ready' keeps every legacy
            // row (and the legacy insert path, which only writes rows on
            // success) fully download-compatible without backfill.
            $table->string('status', 20)->default('ready')->index()->after('type');
            $table->string('batch_id', 100)->nullable()->index()->after('status');
            $table->unsignedInteger('photo_count')->nullable()->after('total_pages');
        });
    }

    public function down(): void
    {
        Schema::table('package_revisions', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropIndex(['batch_id']);
            $table->dropColumn(['status', 'batch_id', 'photo_count']);
        });
    }
};
