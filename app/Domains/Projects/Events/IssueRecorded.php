<?php

declare(strict_types=1);

namespace App\Domains\Projects\Events;

use App\Domains\Projects\Models\IssueHistory;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IssueRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly IssueHistory $issue) {}
}
