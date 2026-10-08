<?php

declare(strict_types=1);

namespace App\Domains\CustomerPortal\Listeners;

use App\Domains\CustomerPortal\Events\CustomerAccessGranted;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\CustomerPortal\Notifications\ManagerCustomerAccessNotification;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyAdminsOnManagerCustomerAccessGrant implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(CustomerAccessGranted $event): void
    {
        $access = CustomerProjectAccess::query()
            ->with(['user', 'project', 'grantedBy'])
            ->find($event->access->id);

        if ($access === null) {
            return;
        }

        $granter = $access->grantedBy;
        if ($granter === null) {
            return;
        }

        // Only notify when granted by a manager (not by an admin)
        if (! $granter->hasRole(Role::MANAGER->value) || $granter->hasRole(Role::ADMIN->value)) {
            return;
        }

        $customer = $access->user;
        $project = $access->project;
        if ($customer === null || $project === null) {
            return;
        }

        User::role(Role::ADMIN->value)
            ->get()
            ->each(function (User $admin) use ($granter, $customer, $project): void {
                $admin->notify(new ManagerCustomerAccessNotification(
                    action: 'granted',
                    manager: $granter,
                    customer: $customer,
                    project: $project,
                ));
            });
    }
}
