<?php

declare(strict_types=1);

namespace App\Domains\Customers\Actions;

use App\Domains\Customers\DTOs\CreateCustomerEngineerDTO;
use App\Domains\Customers\Models\CustomerEngineer;
use App\Domains\Users\Actions\CreateUserAction;
use App\Domains\Users\DTOs\CreateUserDTO;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class CreateCustomerEngineerAction
{
    public function __construct(
        private readonly SetDefaultEngineerAction $setDefaultEngineerAction,
        private readonly CreateUserAction $createUserAction,
    ) {}

    /**
     * An engineer IS a customer account: the engineer row and its linked
     * portal User (role "customer") are created together in one transaction,
     * so the credentials Edwin receives always work.
     */
    public function execute(CreateCustomerEngineerDTO $dto): CustomerEngineer
    {
        return DB::transaction(function () use ($dto): CustomerEngineer {
            $trashed = CustomerEngineer::onlyTrashed()
                ->where('customer_id', $dto->customerId)
                ->where('market_id', $dto->marketId)
                ->where('email', $dto->email)
                ->first();
            if ($trashed !== null) {
                $suffix = '-del-'.$trashed->id;
                $trashed->email = substr($trashed->email, 0, 255 - strlen($suffix)).$suffix;
                $trashed->save();
            }

            User::releaseTrashedEmail($dto->email);

            $user = $this->createUserAction->execute(new CreateUserDTO(
                firstName: $dto->firstName,
                lastName: $dto->lastName,
                email: $dto->email,
                password: $dto->password,
                role: Role::CUSTOMER->value,
                isActive: $dto->isActive,
                company: $dto->company,
                phone: $dto->phone,
                profilePhoto: $dto->profilePhoto,
            ));

            $engineer = CustomerEngineer::create([
                'customer_id' => $dto->customerId,
                'market_id' => $dto->marketId,
                'name' => trim("{$dto->firstName} {$dto->lastName}"),
                'email' => $dto->email,
                'phone' => $dto->phone,
                'title' => $dto->title,
                'is_default' => false,
                'user_id' => $user->id,
                'is_active' => $dto->isActive,
                'created_by' => Auth::id(),
            ]);

            $hasDefault = CustomerEngineer::query()
                ->where('customer_id', $dto->customerId)
                ->where('market_id', $dto->marketId)
                ->where('is_default', true)
                ->where('id', '!=', $engineer->id)
                ->exists();

            if ($dto->isDefault || ! $hasDefault) {
                return $this->setDefaultEngineerAction->execute($engineer);
            }

            return $engineer;
        });
    }
}
