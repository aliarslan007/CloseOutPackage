<?php

declare(strict_types=1);

namespace App\Domains\CustomerPortal\Actions;

use App\Domains\ChangeOrders\Models\ChangeOrderRecipient;
use App\Domains\CustomerPortal\Events\CustomerAccessRevoked;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class RevokeCustomerProjectAccessAction
{
    public function execute(User $customer, Project $project, ?User $revokedBy = null): void
    {
        $actor = $revokedBy ?? Auth::user();

        DB::transaction(function () use ($customer, $project, $actor): void {
            CustomerProjectAccess::query()
                ->where('user_id', $customer->id)
                ->where('project_id', $project->id)
                ->delete();

            ChangeOrderRecipient::query()
                ->where('user_id', $customer->id)
                ->whereHas('changeOrder', fn ($query) => $query->where('project_id', $project->id))
                ->where(fn ($query) => $query->whereNull('token_expires_at')->orWhere('token_expires_at', '>', now()))
                ->update(['token_expires_at' => now()]);

            CustomerAccessRevoked::dispatch($customer, $project, $actor);

            activity('security')
                ->performedOn($project)
                ->causedBy($actor)
                ->event('customer.access.revoked')
                ->withProperties(['customer_id' => $customer->id])
                ->log('Customer project access revoked');
        });
    }
}
