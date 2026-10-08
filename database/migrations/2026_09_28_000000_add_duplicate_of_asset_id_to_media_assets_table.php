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
            // Set when this asset's bytes were already present in the library.
            // The row is a pointer, not a second copy: it holds no file of its
            // own and is hidden from library listings. File access resolves
            // through to the canonical asset. Null = a normal, unique asset.
            $table->foreignId('duplicate_of_asset_id')
                ->nullable()
                ->after('file_hash')
                ->constrained('media_assets')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('duplicate_of_asset_id');
        });
    }
};
