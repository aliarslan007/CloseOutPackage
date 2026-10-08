<?php

declare(strict_types=1);

namespace App\Domains\CustomerPortal\Listeners;

use App\Domains\CustomerPortal\Events\CustomerAccessRevoked;
use App\Domains\CustomerPortal\Notifications\CustomerPortalAccessRevokedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyCustomerOnAccessRevoke implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(CustomerAccessRevoked $event): void
    {
        $event->customer->notify(new CustomerPortalAccessRevokedNotification(
            customer: $event->customer,
            project: $event->project,
        ));
    }
}
