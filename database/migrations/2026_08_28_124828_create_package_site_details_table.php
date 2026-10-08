<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('package_site_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('closeout_package_id')->constrained('closeout_packages')->cascadeOnDelete();
            $table->string('source', 20);
            $table->string('site_type', 20);
            $table->unsignedBigInteger('auth_site_id')->nullable();
            $table->string('name', 255);
            $table->string('region', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('closeout_package_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_site_details');
    }
};
