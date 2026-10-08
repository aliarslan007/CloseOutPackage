<?php

declare(strict_types=1);

namespace App\Domains\Customers\Controllers;

use App\Domains\Customers\Actions\CreateCustomerAction;
use App\Domains\Customers\Actions\DeleteCustomerAction;
use App\Domains\Customers\Actions\UpdateCustomerAction;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Requests\StoreCustomerRequest;
use App\Domains\Customers\Requests\UpdateCustomerRequest;
use App\Http\Controllers\Controller;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CreateCustomerAction $createCustomerAction,
        private readonly UpdateCustomerAction $updateCustomerAction,
        private readonly DeleteCustomerAction $deleteCustomerAction,
    ) {}

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        try {
            $customer = $this->createCustomerAction->execute($request->toDTO());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'code' => 'A customer with this code already exists.',
            ]);
        }

        return redirect()->back()->with('success', "Customer \"{$customer->name}\" created.");
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        try {
            $this->updateCustomerAction->execute($customer, $request->toDTO());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'code' => 'A customer with this code already exists.',
            ]);
        }

        return redirect()->back()->with('success', 'Customer updated.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $this->deleteCustomerAction->execute($customer);

        return redirect()->back()->with('success', 'Customer deleted.');
    }
}
