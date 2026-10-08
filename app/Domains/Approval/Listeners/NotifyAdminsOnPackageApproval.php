<?php

declare(strict_types=1);

namespace App\Domains\Approval\Listeners;

use App\Domains\Approval\Notifications\PackageFinalApprovalNotification;
use App\Domains\CloseoutPackages\Events\PackageApproved;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyAdminsOnPackageApproval implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(PackageApproved $event): void
    {
        User::role(Role::ADMIN->value)
            ->get()
            ->each(function (User $admin) use ($event): void {
                $admin->notify(new PackageFinalApprovalNotification($event->package));
            });
    }
}
