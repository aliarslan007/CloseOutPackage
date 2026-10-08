<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_checklist_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('closeout_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->constrained()->restrictOnDelete();
            $table->boolean('answer')->nullable();
            $table->text('explain')->nullable();
            $table->timestamps();

            $table->unique(
                ['closeout_package_id', 'checklist_item_id'],
                'package_checklist_responses_package_item_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_checklist_responses');
    }
};
