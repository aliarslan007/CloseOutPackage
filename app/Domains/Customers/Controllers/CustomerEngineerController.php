<?php

declare(strict_types=1);

namespace App\Domains\Customers\Controllers;

use App\Domains\Customers\Actions\CreateCustomerEngineerAction;
use App\Domains\Customers\Actions\DeleteCustomerEngineerAction;
use App\Domains\Customers\Actions\UpdateCustomerEngineerAction;
use App\Domains\Customers\Models\CustomerEngineer;
use App\Domains\Customers\Requests\StoreCustomerEngineerRequest;
use App\Domains\Customers\Requests\UpdateCustomerEngineerRequest;
use App\Http\Controllers\Controller;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class CustomerEngineerController extends Controller
{
    public function __construct(
        private readonly CreateCustomerEngineerAction $createCustomerEngineerAction,
        private readonly UpdateCustomerEngineerAction $updateCustomerEngineerAction,
        private readonly DeleteCustomerEngineerAction $deleteCustomerEngineerAction,
    ) {}

    public function store(StoreCustomerEngineerRequest $request): RedirectResponse
    {
        try {
            $engineer = $this->createCustomerEngineerAction->execute($request->toDTO());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'email' => 'An engineer with this email already exists for this customer and region.',
            ]);
        }

        return redirect()->back()->with('success', "Engineer \"{$engineer->name}\" added with a Customer Portal account.");
    }

    public function update(UpdateCustomerEngineerRequest $request, CustomerEngineer $engineer): RedirectResponse
    {
        try {
            $this->updateCustomerEngineerAction->execute($engineer, $request->toDTO());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'email' => 'An engineer with this email already exists for this customer and region.',
            ]);
        }

        return redirect()->back()->with('success', 'Engineer updated.');
    }

    public function destroy(CustomerEngineer $engineer): RedirectResponse
    {
        $this->authorize('delete', $engineer);

        $this->deleteCustomerEngineerAction->execute($engineer);

        return redirect()->back()->with('success', 'Engineer removed.');
    }
}
