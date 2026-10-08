<?php

declare(strict_types=1);

namespace App\Domains\Approval\Events;

use App\Domains\Photos\Models\Photo;
use App\Domains\Users\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReplacementRequested implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Photo $photo,
        public readonly User $manager,
    ) {}
}
