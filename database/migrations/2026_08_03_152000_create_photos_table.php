<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('closeout_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_category_selection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('position')->default(1);
            $table->string('status', 40)->default('pending');
            $table->decimal('gps_latitude', 10, 7)->nullable();
            $table->decimal('gps_longitude', 10, 7)->nullable();
            $table->float('gps_accuracy')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('flagged_at')->nullable();
            $table->foreignId('flagged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('flag_reason', 1000)->nullable();
            $table->timestamp('replaced_at')->nullable();
            $table->foreignId('replaced_by_photo_id')->nullable()->constrained('photos')->nullOnDelete();
            $table->boolean('is_approved')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('uploaded_by');
            $table->index('is_approved');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
