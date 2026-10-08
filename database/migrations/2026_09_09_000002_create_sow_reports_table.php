<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sow_reports', function (Blueprint $table): void {
            $table->id();
            // Nullable — SOW can be standalone (no project) or linked to a project
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('market_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title');

            // Page 1 — Cover Details
            $table->string('site_name')->nullable();
            $table->date('report_date')->nullable();
            $table->string('fiber_provider', 80)->default('VZB');
            $table->foreignId('cover_media_asset_id')->nullable()
                ->constrained('media_assets')->nullOnDelete();

            // Page 2 — Table 1: Project Data
            $table->string('site_address', 500)->nullable();
            $table->string('site_city', 120)->nullable();
            $table->string('site_county', 120)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('project_description')->nullable();

            // Page 2 — Table 2: Survey Data
            $table->date('field_survey_date')->nullable();
            $table->string('provider_contact_name', 255)->nullable();
            $table->string('provider_contact_phone', 50)->nullable();
            $table->string('provider_contact_email', 255)->nullable();
            $table->string('demarcation', 255)->nullable();
            $table->string('ps_number', 80)->nullable();
            $table->string('survey_contractor_name', 255)->nullable();
            $table->string('survey_contact_name', 255)->nullable();
            $table->string('survey_contact_phone', 50)->nullable();
            $table->string('survey_contact_email', 255)->nullable();
            $table->text('scope_of_work_description')->nullable();
            $table->text('site_access_notes')->nullable();

            // Workflow & Revisions
            $table->string('status')->default('draft');
            $table->string('pdf_generation_status')->default('idle');
            $table->unsignedSmallInteger('revision')->default(1);
            $table->date('revision_date')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['market_id', 'status']);
            $table->index('created_by');
            $table->index(['status', 'pdf_generation_status']);
            $table->index('ps_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sow_reports');
    }
};
