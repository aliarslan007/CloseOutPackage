<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('change_orders', function (Blueprint $table): void {
            // Nullable: new change orders keep NULL and sort to the top
            // (secondary id DESC), which preserves the pre-feature
            // newest-first display order without touching create paths.
            $table->unsignedInteger('sort_order')->nullable()->index()->after('total_amount');
        });

        // Backfill so the first render matches the previous latest() order:
        // newest change order = 0, next = 1, ...
        $ids = DB::table('change_orders')->orderByDesc('id')->pluck('id');
        foreach ($ids as $position => $id) {
            DB::table('change_orders')->where('id', $id)->update(['sort_order' => $position]);
        }
    }

    public function down(): void
    {
        Schema::table('change_orders', function (Blueprint $table): void {
            $table->dropIndex(['sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
