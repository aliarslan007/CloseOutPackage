<?php

declare(strict_types=1);

namespace App\Domains\Users\Actions;

use App\Domains\Users\Events\UserStatusChanged;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class ToggleUserActiveAction
{
    public function execute(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->update(['is_active' => ! $user->is_active]);

            activity('user')
                ->causedBy(Auth::user())
                ->performedOn($user)
                ->event($user->is_active ? 'user.activated' : 'user.deactivated')
                ->log($user->is_active ? 'User activated' : 'User deactivated');

            UserStatusChanged::dispatch($user, (bool) $user->is_active);
        });
    }
}
