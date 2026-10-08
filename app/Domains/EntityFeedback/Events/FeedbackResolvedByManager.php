<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Events;

use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\Users\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class FeedbackResolvedByManager
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly EntityFeedbackRound $round,
        public readonly User $actor,
    ) {}
}
