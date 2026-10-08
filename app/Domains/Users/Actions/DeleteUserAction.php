<?php

declare(strict_types=1);

namespace App\Domains\Users\Actions;

use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class DeleteUserAction
{
    public function execute(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->delete();

            activity('user')
                ->causedBy(Auth::user())
                ->performedOn($user)
                ->event('user.deleted')
                ->log('User deleted locally');
        });
    }
}
