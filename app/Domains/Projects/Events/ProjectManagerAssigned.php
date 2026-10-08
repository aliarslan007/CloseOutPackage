<?php

declare(strict_types=1);

namespace App\Domains\Projects\Events;

use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectManagerAssigned implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Project $project,
        public readonly User $manager,
    ) {}
}
