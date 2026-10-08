<?php

declare(strict_types=1);

namespace App\Domains\CustomerPortal\Listeners;

use App\Domains\CustomerPortal\Events\CustomerAccessRevoked;
use App\Domains\CustomerPortal\Notifications\ManagerCustomerAccessNotification;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyAdminsOnManagerCustomerAccessRevoke implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(CustomerAccessRevoked $event): void
    {
        $revoker = $event->revokedBy;
        if ($revoker === null) {
            return;
        }

        // Only notify when revoked by a manager (not by an admin)
        if (! $revoker->hasRole(Role::MANAGER->value) || $revoker->hasRole(Role::ADMIN->value)) {
            return;
        }

        User::role(Role::ADMIN->value)
            ->get()
            ->each(function (User $admin) use ($revoker, $event): void {
                $admin->notify(new ManagerCustomerAccessNotification(
                    action: 'revoked',
                    manager: $revoker,
                    customer: $event->customer,
                    project: $event->project,
                ));
            });
    }
}
