<?php

declare(strict_types=1);

namespace App\Domains\Users\Actions;

use App\Domains\Users\DTOs\SsoUserSyncDTO;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;

final class SsoInboundUserSyncAction
{
    public function execute(SsoUserSyncDTO $dto): User
    {
        return DB::transaction(function () use ($dto): User {
            $user = User::query()
                ->where('uuid', $dto->uuid)
                ->orWhere('email', $dto->email)
                ->first();

            if ($user === null) {
                $user = User::create([
                    'uuid' => $dto->uuid,
                    'name' => User::fullName($dto->firstName, $dto->lastName),
                    'email' => $dto->email,
                    'password' => (string) ($dto->password ?? ''),
                ]);
            } else {
                $user->update([
                    'uuid' => $dto->uuid,
                    'name' => User::fullName($dto->firstName, $dto->lastName),
                    'email' => $dto->email,
                    'password' => $dto->password ?? $user->password,
                ]);
            }

            activity('user')
                ->performedOn($user)
                ->event('user.synced')
                ->log('User synced from SSO');

            return $user;
        });
    }
}
