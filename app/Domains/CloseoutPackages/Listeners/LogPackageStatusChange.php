<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Listeners;

use App\Domains\CloseoutPackages\Events\PackageApproved;
use App\Domains\CloseoutPackages\Events\PackageRejected;
use App\Domains\CloseoutPackages\Events\PackageResubmitted;
use App\Domains\CloseoutPackages\Events\PackageSubmitted;

class LogPackageStatusChange
{
    public function handle(PackageApproved|PackageRejected|PackageResubmitted|PackageSubmitted $event): void
    {
        $meta = $this->meta($event);

        activity($meta['category'])
            ->performedOn($event->package)
            ->event($meta['event'])
            ->log($meta['description']);
    }

    /**
     * @return array{category: string, event: string, description: string}
     */
    private function meta(PackageApproved|PackageRejected|PackageResubmitted|PackageSubmitted $event): array
    {
        return match (true) {
            $event instanceof PackageApproved => [
                'category' => 'approval',
                'event' => 'package.approved',
                'description' => 'Package approved',
            ],
            $event instanceof PackageRejected => [
                'category' => 'approval',
                'event' => 'package.rejected',
                'description' => 'Package rejected',
            ],
            $event instanceof PackageResubmitted => [
                'category' => 'project',
                'event' => 'package.resubmitted',
                'description' => 'Package resubmitted after replacements',
            ],
            default => [
                'category' => 'project',
                'event' => 'package.submitted',
                'description' => 'Package submitted',
            ],
        };
    }
}
