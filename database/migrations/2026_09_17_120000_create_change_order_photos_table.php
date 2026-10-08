<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_order_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('change_order_id')->constrained('change_orders')->cascadeOnDelete();
            // Kept for dedupe + traceability back to the library asset the file was copied from.
            $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('original_name');
            $table->string('path')->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();

            // One library asset can only be attached once per change order.
            $table->unique(['change_order_id', 'media_asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_order_photos');
    }
};
