<?php

declare(strict_types=1);

namespace App\Domains\Users\Actions;

use App\Domains\Users\DTOs\UpdateUserDTO;
use App\Domains\Users\Events\UserUpdated;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\SsoUserSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class UpdateUserAction
{
    public function __construct(private readonly SsoUserSyncService $ssoSync) {}

    public function execute(User $user, UpdateUserDTO $dto): void
    {
        DB::transaction(function () use ($user, $dto): void {
            $emailChanged = $dto->email !== $user->email;

            $user->update([
                'name' => User::fullName($dto->firstName, $dto->lastName),
                'email' => $dto->email,
                'company' => $dto->company,
                'phone' => $dto->phone,
                'is_active' => $dto->isActive,
            ]);

            $user->syncRoles([$dto->role]);

            if ($dto->profilePhoto !== null) {
                $user->addMedia($dto->profilePhoto)->toMediaCollection(User::PROFILE_PHOTO_COLLECTION);
            }

            activity('user')
                ->causedBy(Auth::user())
                ->performedOn($user)
                ->event('user.updated')
                ->withProperties(['role' => $dto->role])
                ->log('User updated');

            if ($emailChanged) {
                // Email is a login key: sync it now so the change is all-or-nothing.
                $this->ssoSync->syncNow($user);
            } else {
                // Name/role/status are not login keys — never block on AuthCenter.
                UserUpdated::dispatch($user);
            }
        });
    }
}
