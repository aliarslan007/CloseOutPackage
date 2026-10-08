<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sow_checklist_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sow_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sow_checklist_item_id')->constrained()->cascadeOnDelete();
            $table->string('response')->nullable(); // 'yes' | 'no' | 'n_a'
            $table->text('explanation')->nullable();
            $table->timestamps();

            $table->unique(['sow_report_id', 'sow_checklist_item_id'], 'sow_responses_report_item_unique');
            $table->index('sow_report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sow_checklist_responses');
    }
};
