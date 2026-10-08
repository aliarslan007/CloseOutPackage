<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sow_checklist_items', function (Blueprint $table): void {
            $table->id();
            // Nullable = Global/Universal question; otherwise scoped to one market
            $table->foreignId('market_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label', 1000);
            $table->boolean('has_explain_field')->default(true);
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['market_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sow_checklist_items');
    }
};
