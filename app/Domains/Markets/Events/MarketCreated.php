<?php

declare(strict_types=1);

namespace App\Domains\Markets\Events;

use App\Domains\Markets\Models\Market;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MarketCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Market $market) {}
}
