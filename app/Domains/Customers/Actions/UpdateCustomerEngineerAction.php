<?php

declare(strict_types=1);

namespace App\Domains\Customers\Actions;

use App\Domains\Customers\DTOs\UpdateCustomerEngineerDTO;
use App\Domains\Customers\Models\CustomerEngineer;
use App\Domains\Users\Actions\ChangeUserPasswordAction;
use App\Domains\Users\Actions\CreateUserAction;
use App\Domains\Users\DTOs\ChangeUserPasswordDTO;
use App\Domains\Users\DTOs\CreateUserDTO;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\SsoUserSyncService;
use App\Shared\Enums\Role;
use Illuminate\Support\Facades\DB;

final class UpdateCustomerEngineerAction
{
    public function __construct(
        private readonly SetDefaultEngineerAction $setDefaultEngineerAction,
        private readonly ChangeUserPasswordAction $changeUserPasswordAction,
        private readonly CreateUserAction $createUserAction,
        private readonly SsoUserSyncService $ssoSync,
    ) {}

    public function execute(CustomerEngineer $engineer, UpdateCustomerEngineerDTO $dto): CustomerEngineer
    {
        return DB::transaction(function () use ($engineer, $dto): CustomerEngineer {
            $attributes = $this->resolveAttributes($dto);

            if ($attributes !== []) {
                $engineer->update($attributes);
            }

            $this->syncPortalAccount($engineer, $dto);

            if ($dto->isDefault === true) {
                return $this->setDefaultEngineerAction->execute($engineer);
            }

            if ($dto->isDefault === false && $engineer->is_default) {
                $engineer->update(['is_default' => false]);
            }

            return $engineer->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAttributes(UpdateCustomerEngineerDTO $dto): array
    {
        $attributes = [];

        if ($dto->marketId !== null) {
            $attributes['market_id'] = $dto->marketId;
        }

        if ($dto->firstName !== null && $dto->lastName !== null) {
            $attributes['name'] = trim("{$dto->firstName} {$dto->lastName}");
        }

        if ($dto->email !== null) {
            $attributes['email'] = $dto->email;
        }

        if ($dto->phone !== null) {
            $attributes['phone'] = $dto->phone;
        }

        if ($dto->title !== null) {
            $attributes['title'] = $dto->title;
        }

        if ($dto->isActive !== null) {
            $attributes['is_active'] = $dto->isActive;
        }

        return $attributes;
    }

    /**
     * Keep the linked portal account (name, email, phone, active state,
     * password) in step with the engineer row. Engineers created before
     * portal accounts existed get one as soon as a password is set here.
     */
    private function syncPortalAccount(CustomerEngineer $engineer, UpdateCustomerEngineerDTO $dto): void
    {
        $user = $engineer->user()->first();

        if ($user === null) {
            if ($dto->password === null || $dto->firstName === null || $dto->lastName === null) {
                return;
            }

            User::releaseTrashedEmail($engineer->email);

            $user = $this->createUserAction->execute(new CreateUserDTO(
                firstName: $dto->firstName,
                lastName: $dto->lastName,
                email: $engineer->email,
                password: $dto->password,
                role: Role::CUSTOMER->value,
                isActive: $engineer->is_active,
                company: $dto->company,
                phone: $engineer->phone,
                profilePhoto: $dto->profilePhoto,
            ));
            $engineer->update(['user_id' => $user->id]);

            return;
        }

        if ($dto->password !== null) {
            $this->changeUserPasswordAction->execute($user, new ChangeUserPasswordDTO($dto->password));
        }

        if ($dto->profilePhoto !== null) {
            $user->addMedia($dto->profilePhoto)->toMediaCollection(User::PROFILE_PHOTO_COLLECTION);
        }

        $userAttributes = [];

        if ($dto->firstName !== null && $dto->lastName !== null) {
            $userAttributes['name'] = User::fullName($dto->firstName, $dto->lastName);
        }

        if ($dto->email !== null) {
            User::releaseTrashedEmail($dto->email);
            $userAttributes['email'] = $dto->email;
        }

        if ($dto->phone !== null) {
            $userAttributes['phone'] = $dto->phone;
        }

        if ($dto->company !== null) {
            $userAttributes['company'] = $dto->company;
        }

        if ($dto->isActive !== null) {
            $userAttributes['is_active'] = $dto->isActive;
        }

        if ($userAttributes !== []) {
            $emailChanged = array_key_exists('email', $userAttributes)
                && $userAttributes['email'] !== $user->email;

            $user->update($userAttributes);

            if ($emailChanged) {
                // The portal account's email is its login key: sync it now so
                // the engineer edit and AuthCenter never disagree.
                $this->ssoSync->syncNow($user);
            }
        }
    }
}
