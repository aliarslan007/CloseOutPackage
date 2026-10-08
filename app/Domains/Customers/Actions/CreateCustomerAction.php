<?php

declare(strict_types=1);

namespace App\Domains\Customers\Actions;

use App\Domains\Customers\DTOs\CreateCustomerDTO;
use App\Domains\Customers\Models\Customer;
use Illuminate\Support\Facades\Auth;

final class CreateCustomerAction
{
    public function execute(CreateCustomerDTO $dto): Customer
    {
        $trashed = Customer::onlyTrashed()->where('code', $dto->code)->first();
        if ($trashed !== null) {
            $deletedSuffix = '-del-'.$trashed->id;
            $truncatedCode = substr($trashed->code, 0, 20 - strlen($deletedSuffix));
            $trashed->code = $truncatedCode.$deletedSuffix;
            $trashed->save();
        }

        return Customer::create([
            'name' => $dto->name,
            'code' => $dto->code,
            'description' => $dto->description,
            'is_active' => $dto->isActive,
            'created_by' => Auth::id(),
        ]);
    }
}
