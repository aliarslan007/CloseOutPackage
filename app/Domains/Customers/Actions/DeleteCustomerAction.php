<?php

declare(strict_types=1);

namespace App\Domains\Customers\Actions;

use App\Domains\Customers\Models\Customer;
use App\Shared\Exceptions\CustomerHasProjectsException;
use Illuminate\Support\Facades\DB;

final class DeleteCustomerAction
{
    public function execute(Customer $customer): void
    {
        // A soft-deleted customer would leave its projects pointing at a hidden
        // record, so require those projects to be moved first. These are the
        // customer's own projects (any engineer) — the engineer workload chips
        // only show projects assigned to a specific engineer, so this count can
        // be non-zero even when every engineer reads "0 projects".
        $projectCount = $customer->projects()->count();
        if ($projectCount > 0) {
            throw new CustomerHasProjectsException(
                "This customer still has {$projectCount} project".($projectCount === 1 ? '' : 's').
                    '. Reassign or remove them in the Projects module before deleting this customer.',
            );
        }

        DB::transaction(function () use ($customer): void {
            $customer->customerEngineers()->delete();

            $deletedSuffix = '-del-'.$customer->id;
            $maxCodeLength = 20;
            $truncatedCode = substr($customer->code, 0, $maxCodeLength - strlen($deletedSuffix));
            $customer->code = $truncatedCode.$deletedSuffix;
            $customer->save();

            $customer->delete();
        });
    }
}
