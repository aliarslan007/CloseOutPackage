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
            // The all-projects list carries its own sequence, independent of
            // the per-project `sort_order`: a change order has one position
            // in its project's list and one in the global list. Nullable for
            // the same reason — rows created after a reorder hold NULL and
            // sort to the top via the secondary id DESC.
            $table->unsignedInteger('global_sort_order')->nullable()->index()->after('sort_order');
        });

        // Backfill so the first render matches the previous latest() order:
        // newest change order = 0, next = 1, ...
        $ids = DB::table('change_orders')->orderByDesc('id')->pluck('id');
        foreach ($ids as $position => $id) {
            DB::table('change_orders')->where('id', $id)->update(['global_sort_order' => $position]);
        }
    }

    public function down(): void
    {
        Schema::table('change_orders', function (Blueprint $table): void {
            $table->dropIndex(['global_sort_order']);
            $table->dropColumn('global_sort_order');
        });
    }
};
