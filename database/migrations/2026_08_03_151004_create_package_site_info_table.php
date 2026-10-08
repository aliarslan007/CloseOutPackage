<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_site_info', function (Blueprint $table): void {
            $table->unsignedBigInteger('closeout_package_id')->primary();
            $table->foreign('closeout_package_id')->references('id')->on('closeout_packages')->cascadeOnDelete();
            $table->string('site_name', 255)->nullable();
            $table->string('ps_number', 80)->nullable();
            $table->string('project_number', 80)->nullable();
            $table->string('address', 500)->nullable();
            $table->date('complete_date')->nullable();
            $table->string('vzw_engineer_name', 255)->nullable();
            $table->string('vzw_engineer_phone', 50)->nullable();
            $table->string('vzw_engineer_email', 255)->nullable();
            $table->string('author_company', 255)->nullable();
            $table->string('author_name', 255)->nullable();
            $table->string('author_email', 255)->nullable();
            $table->string('author_phone', 50)->nullable();
            $table->string('pm_company', 255)->nullable();
            $table->string('pm_name', 255)->nullable();
            $table->string('pm_email', 255)->nullable();
            $table->string('pm_phone', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_site_info');
    }
};
