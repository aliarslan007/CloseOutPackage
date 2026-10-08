<?php

declare(strict_types=1);

namespace App\Domains\Approval\Events;

use App\Domains\Approval\Enums\ApprovalDecision;
use App\Domains\Photos\Models\Photo;
use App\Domains\Users\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PhotoReviewed implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Photo $photo,
        public readonly ApprovalDecision $decision,
        public readonly User $reviewer,
    ) {}
}
