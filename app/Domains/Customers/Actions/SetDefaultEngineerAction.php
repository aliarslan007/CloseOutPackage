<?php

declare(strict_types=1);

namespace App\Domains\Customers\Actions;

use App\Domains\Customers\Models\CustomerEngineer;
use Illuminate\Support\Facades\DB;

/**
 * Guarantees at most one default engineer per (customer, market) pair by
 * clearing the flag on its siblings inside the same transaction.
 */
final class SetDefaultEngineerAction
{
    public function execute(CustomerEngineer $engineer): CustomerEngineer
    {
        return DB::transaction(function () use ($engineer): CustomerEngineer {
            CustomerEngineer::query()
                ->where('customer_id', $engineer->customer_id)
                ->where('market_id', $engineer->market_id)
                ->whereKeyNot($engineer->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);

            $engineer->update(['is_default' => true]);

            return $engineer->refresh();
        });
    }
}
