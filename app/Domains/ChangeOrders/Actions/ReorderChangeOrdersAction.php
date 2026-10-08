<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Actions;

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\Projects\Models\Project;
use Illuminate\Support\Facades\DB;

final class ReorderChangeOrdersAction
{
    /**
     * Persist one project's display order. Updates are scoped through the
     * project relation so a payload can never touch another project's
     * change orders, even if it somehow reaches the action unvalidated.
     *
     * @param  list<array{id: int, sort_order: int}>  $items
     */
    public function execute(Project $project, array $items): void
    {
        DB::transaction(function () use ($project, $items): void {
            foreach ($items as $entry) {
                $project->changeOrders()
                    ->whereKey($entry['id'])
                    ->update(['sort_order' => $entry['sort_order']]);
            }
        });
    }

    /**
     * Persist the all-projects display order. This is the second, independent
     * sequence: a change order keeps its place in its project's list while
     * moving freely here, so the two pages never fight over one ordering.
     *
     * ponytail: positions are assigned from the payload only, so a manager
     * who sees a market subset writes 0..n-1 over their rows and can share a
     * position with rows outside their scope (ties break newest-first). An
     * admin sees every row, so their order is exact and unique. Ceiling at
     * change-order scale; rebase every row onto a dense sequence if a shared
     * global order for partial viewers is ever needed.
     *
     * @param  list<array{id: int, sort_order: int}>  $items
     */
    public function executeGlobal(array $items): void
    {
        DB::transaction(function () use ($items): void {
            foreach ($items as $entry) {
                ChangeOrder::query()
                    ->whereKey($entry['id'])
                    ->update(['global_sort_order' => $entry['sort_order']]);
            }
        });
    }
}
