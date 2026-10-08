<?php

declare(strict_types=1);

namespace App\Domains\Customers\Actions;

use App\Domains\Customers\DTOs\UpdateCustomerDTO;
use App\Domains\Customers\Models\Customer;

final class UpdateCustomerAction
{
    public function execute(Customer $customer, UpdateCustomerDTO $dto): Customer
    {
        $attributes = [];

        if ($dto->name !== null) {
            $attributes['name'] = $dto->name;
        }

        if ($dto->code !== null) {
            $trashed = Customer::onlyTrashed()->where('code', $dto->code)->first();
            if ($trashed !== null && $trashed->id !== $customer->id) {
                $deletedSuffix = '-del-'.$trashed->id;
                $truncatedCode = substr($trashed->code, 0, 20 - strlen($deletedSuffix));
                $trashed->code = $truncatedCode.$deletedSuffix;
                $trashed->save();
            }
            $attributes['code'] = $dto->code;
        }

        if ($dto->description !== null) {
            $attributes['description'] = $dto->description;
        }

        if ($dto->isActive !== null) {
            $attributes['is_active'] = $dto->isActive;
        }

        if ($attributes !== []) {
            $customer->update($attributes);
        }

        return $customer->refresh();
    }
}
