<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Events;

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\Users\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChangeOrderDecided implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, array{item_id: int, decision: mixed, note?: ?string}>  $decisions
     */
    public function __construct(
        public readonly ChangeOrder $changeOrder,
        public readonly User $decidedBy,
        public readonly array $decisions = [],
    ) {}
}
