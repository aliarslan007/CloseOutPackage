<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('market_id')->constrained()->restrictOnDelete();
            $table->string('name', 255);
            $table->string('ps_number', 80)->nullable();
            $table->string('project_number', 80)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 80)->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('vzw_engineer_name', 255)->nullable();
            $table->string('vzw_engineer_email', 255)->nullable();
            $table->string('vzw_engineer_phone', 50)->nullable();
            $table->string('status', 40)->default('pending');
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->foreignId('manager_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('completed_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('market_id');
            $table->index('manager_id');
            $table->index('status');
            $table->index('ps_number');
            $table->index('project_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
