<?php

declare(strict_types=1);

namespace App\Domains\CustomerPortal\Events;

use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerAccessGranted implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly CustomerProjectAccess $access) {}
}
