<?php

declare(strict_types=1);

namespace App\Domains\Customers\Requests;

use App\Domains\Customers\DTOs\CreateCustomerEngineerDTO;
use App\Domains\Customers\Models\CustomerEngineer;
use App\Domains\Markets\Models\Market;
use App\Domains\Users\Requests\Concerns\ValidatesProfileFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreCustomerEngineerRequest extends FormRequest
{
    use ValidatesProfileFields;

    public function authorize(): bool
    {
        return Gate::allows('create', [CustomerEngineer::class, $this->targetMarket()]);
    }

    /**
     * The region (market) is mandatory: an engineer must be anchored to the
     * territory whose projects they can be defaulted to. The password creates
     * their Customer Portal login — the credentials Edwin forwards to them.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $customerId = is_numeric($this->input('customer_id')) ? (int) $this->input('customer_id') : 0;
        $marketId = is_numeric($this->input('market_id')) ? (int) $this->input('market_id') : 0;

        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'market_id' => ['required', 'integer', Rule::exists('markets', 'id')->whereNull('deleted_at')],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('customer_engineers', 'email')
                    ->where('customer_id', $customerId)
                    ->where('market_id', $marketId)
                    ->whereNull('deleted_at'),
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:50'],
            'title' => ['nullable', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'profile_photo' => $this->profilePhotoRule(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'This email already belongs to an account. Use a different email.',
        ];
    }

    public function toDTO(): CreateCustomerEngineerDTO
    {
        $customerId = $this->validated('customer_id');
        $marketId = $this->validated('market_id');
        $firstName = $this->validated('first_name');
        $lastName = $this->validated('last_name');
        $email = $this->validated('email');
        $password = $this->validated('password');
        $phone = $this->validated('phone');
        $title = $this->validated('title');
        $company = $this->validated('company');

        return new CreateCustomerEngineerDTO(
            customerId: is_numeric($customerId) ? (int) $customerId : 0,
            marketId: is_numeric($marketId) ? (int) $marketId : 0,
            firstName: is_string($firstName) ? $firstName : '',
            lastName: is_string($lastName) ? $lastName : '',
            email: is_string($email) ? $email : '',
            password: is_string($password) ? $password : '',
            phone: is_string($phone) ? $phone : null,
            title: is_string($title) ? $title : null,
            company: is_string($company) ? $company : null,
            isDefault: $this->boolean('is_default'),
            isActive: $this->boolean('is_active', true),
            profilePhoto: $this->file('profile_photo'),
        );
    }

    private function targetMarket(): ?Market
    {
        $marketId = $this->input('market_id');

        return is_numeric($marketId) ? Market::query()->find((int) $marketId) : null;
    }
}
