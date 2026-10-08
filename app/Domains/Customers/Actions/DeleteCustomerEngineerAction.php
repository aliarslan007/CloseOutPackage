<?php

declare(strict_types=1);

namespace App\Domains\Customers\Actions;

use App\Domains\Customers\Models\CustomerEngineer;
use Illuminate\Support\Facades\DB;

final class DeleteCustomerEngineerAction
{
    /**
     * Soft delete only: projects keep their legacy engineer snapshot columns,
     * so historic PDFs and emails never change. The linked portal account is
     * the engineer's login — once their last active engineer record is gone
     * (across every customer/region), the login is removed with it.
     */
    public function execute(CustomerEngineer $engineer): void
    {
        DB::transaction(function () use ($engineer): void {
            $user = $engineer->user()->first();

            $engineer->delete();

            if ($user !== null && ! CustomerEngineer::query()->where('user_id', $user->id)->exists()) {
                $user->delete();
            }
        });
    }
}
