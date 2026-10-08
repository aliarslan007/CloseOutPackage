<?php

declare(strict_types=1);

namespace App\Domains\Users\Actions;

use App\Domains\Users\DTOs\ChangeUserPasswordDTO;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\SsoUserSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class ChangeUserPasswordAction
{
    public function __construct(private readonly SsoUserSyncService $ssoSync) {}

    public function execute(User $user, ChangeUserPasswordDTO $dto): void
    {
        DB::transaction(function () use ($user, $dto): void {
            $user->password = $dto->password;
            $user->save();

            activity('user')
                ->causedBy(Auth::user())
                ->performedOn($user)
                ->event('user.password_changed')
                ->log('User password changed');

            // The password is what AuthCenter authenticates against, so the
            // change only stands if AuthCenter accepted it. A throw rolls the
            // transaction back and the user sees why.
            $this->ssoSync->syncNow($user);
        });
    }
}
