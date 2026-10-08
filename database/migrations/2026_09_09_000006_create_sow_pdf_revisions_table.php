<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sow_pdf_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sow_report_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('revision_number');
            $table->date('revision_date');
            $table->string('storage_disk')->default('private'); // 's3' in production
            $table->string('storage_path');
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['sow_report_id', 'revision_number']);
            $table->index('sow_report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sow_pdf_revisions');
    }
};
