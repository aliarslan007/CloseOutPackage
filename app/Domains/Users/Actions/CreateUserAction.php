<?php

declare(strict_types=1);

namespace App\Domains\Users\Actions;

use App\Domains\Users\DTOs\CreateUserDTO;
use App\Domains\Users\Events\UserCreated;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateUserAction
{
    public function execute(CreateUserDTO $dto): User
    {
        $user = DB::transaction(function () use ($dto): User {
            $user = User::create([
                'uuid' => (string) Str::uuid(),
                'name' => User::fullName($dto->firstName, $dto->lastName),
                'email' => $dto->email,
                'password' => $dto->password,
                'company' => $dto->company,
                'phone' => $dto->phone,
                'is_active' => $dto->isActive,
            ]);

            $user->assignRole($dto->role);

            if ($dto->profilePhoto !== null) {
                $user->addMedia($dto->profilePhoto)->toMediaCollection(User::PROFILE_PHOTO_COLLECTION);
            }

            activity('user')
                ->causedBy(Auth::user())
                ->performedOn($user)
                ->event('user.created')
                ->withProperties(['role' => $dto->role])
                ->log('User created');

            UserCreated::dispatch($user);

            return $user;
        });

        return $user;
    }
}
