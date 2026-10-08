<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('change_order_items', function (Blueprint $table): void {
            // JSON-encoded list of up to ChangeOrder::MAX_ITEM_VENDORS company names.
            $table->text('vendor_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('change_order_items', function (Blueprint $table): void {
            $table->string('vendor_name', 255)->nullable()->change();
        });
    }
};
