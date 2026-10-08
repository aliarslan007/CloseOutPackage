<?php

declare(strict_types=1);

namespace App\Domains\CustomerPortal\Listeners;

use App\Domains\CustomerPortal\Events\CustomerAccessGranted;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\CustomerPortal\Notifications\CustomerPortalAccessNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyCustomerOnAccessGrant implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(CustomerAccessGranted $event): void
    {
        $access = CustomerProjectAccess::query()->with('user')->find($event->access->id);

        if ($access === null) {
            return;
        }

        $customer = $access->user;

        if ($customer === null) {
            return;
        }

        $customer->notify(new CustomerPortalAccessNotification($access));
    }
}
