<?php

declare(strict_types=1);

namespace App\Domains\Users\Actions;

use App\Domains\Users\DTOs\UpdateProfileDTO;
use App\Domains\Users\Events\UserUpdated;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\SsoUserSyncService;
use Illuminate\Support\Facades\DB;

final class UpdateProfileAction
{
    public function __construct(private readonly SsoUserSyncService $ssoSync) {}

    public function execute(User $user, UpdateProfileDTO $dto): void
    {
        DB::transaction(function () use ($user, $dto): void {
            $emailChanged = $dto->email !== $user->email;

            $user->name = User::fullName($dto->firstName, $dto->lastName);
            $user->email = $dto->email;
            $user->company = $dto->company;
            $user->phone = $dto->phone;

            if ($emailChanged) {
                $user->email_verified_at = null;
            }

            $user->save();

            if ($dto->profilePhoto !== null) {
                $user->addMedia($dto->profilePhoto)
                    ->toMediaCollection(User::PROFILE_PHOTO_COLLECTION);
            }

            activity('user')
                ->causedBy($user)
                ->performedOn($user)
                ->event('profile.updated')
                ->log('Profile updated');

            if ($emailChanged) {
                // Email is a login key: sync it now so the change is all-or-nothing.
                $this->ssoSync->syncNow($user);
            } else {
                // Name/company/phone/photo are not login keys — never block on AuthCenter.
                UserUpdated::dispatch($user);
            }
        });
    }
}
