<?php

declare(strict_types=1);

namespace App\Domains\CustomerPortal\Events;

use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerAccessRevoked implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $customer,
        public readonly Project $project,
        public readonly ?User $revokedBy = null,
    ) {}
}
