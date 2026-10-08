<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('change_order_id')->constrained('change_orders')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('description');
            $table->string('vendor_name', 255)->nullable();
            $table->string('category', 100)->nullable();
            $table->decimal('amount', 10, 2);
            $table->boolean('is_included')->default(true);
            $table->string('customer_decision', 50)->nullable()->index();
            $table->text('customer_decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['change_order_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_order_items');
    }
};
