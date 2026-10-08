<?php

declare(strict_types=1);

namespace App\Domains\Users\Events;

use App\Domains\Users\Contracts\ShouldSyncUserToSso;
use App\Domains\Users\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserCreated implements ShouldDispatchAfterCommit, ShouldSyncUserToSso
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly User $user) {}

    public function user(): User
    {
        return $this->user;
    }

    public function ssoAction(): string
    {
        return 'created';
    }
}
