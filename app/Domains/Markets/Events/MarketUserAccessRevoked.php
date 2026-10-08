<?php

declare(strict_types=1);

namespace App\Domains\Markets\Events;

use App\Domains\Markets\Models\Market;
use App\Domains\Users\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MarketUserAccessRevoked implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Market $market,
        public readonly User $user,
    ) {}
}
