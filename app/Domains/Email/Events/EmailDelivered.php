<?php

declare(strict_types=1);

namespace App\Domains\Email\Events;

use App\Domains\Email\Models\EmailLog;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmailDelivered implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly EmailLog $log) {}
}
