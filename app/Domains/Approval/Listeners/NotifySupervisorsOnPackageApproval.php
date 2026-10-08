<?php

declare(strict_types=1);

namespace App\Domains\Approval\Listeners;

use App\Domains\Approval\Notifications\PackageFinalApprovalNotification;
use App\Domains\CloseoutPackages\Events\PackageApproved;
use App\Domains\Projects\Models\Project;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifySupervisorsOnPackageApproval implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(PackageApproved $event): void
    {
        $event->package->loadMissing('project.fieldSupervisors');

        /** @var Project|null $project */
        $project = $event->package->project;

        if ($project === null) {
            return;
        }

        foreach ($project->fieldSupervisors as $supervisor) {
            $supervisor->notify(new PackageFinalApprovalNotification($event->package));
        }
    }
}
