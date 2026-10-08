<?php

declare(strict_types=1);

namespace App\Domains\Customers\Requests;

use App\Domains\Customers\DTOs\UpdateCustomerEngineerDTO;
use App\Domains\Customers\Models\CustomerEngineer;
use App\Domains\Markets\Models\Market;
use App\Domains\Users\Requests\Concerns\ValidatesProfileFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCustomerEngineerRequest extends FormRequest
{
    use ValidatesProfileFields;

    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('engineer'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CustomerEngineer $engineer */
        $engineer = $this->route('engineer');
        $requestedMarketId = $this->input('market_id', $engineer->market_id);
        $marketId = is_numeric($requestedMarketId) ? (int) $requestedMarketId : $engineer->market_id;

        return [
            'market_id' => ['sometimes', 'required', 'integer', Rule::exists('markets', 'id')->whereNull('deleted_at')],
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('customer_engineers', 'email')
                    ->where('customer_id', $engineer->customer_id)
                    ->where('market_id', $marketId)
                    ->whereNull('deleted_at')
                    ->ignore($engineer->getKey()),
                Rule::unique('users', 'email')
                    ->ignore($engineer->user_id)
                    ->whereNull('deleted_at'),
            ],
            // Blank password = keep the portal account's current credentials.
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
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
            'email.unique' => 'This email already belongs to another account. Use a different email.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var CustomerEngineer $engineer */
            $engineer = $this->route('engineer');
            $marketId = $this->input('market_id');

            if (! is_numeric($marketId) || (int) $marketId === (int) $engineer->market_id) {
                return;
            }

            $market = Market::query()->find((int) $marketId);
            $user = $this->user();

            if ($market === null || $user === null) {
                return;
            }

            if (! Gate::allows('manageRegion', [CustomerEngineer::class, $market])) {
                $validator->errors()->add(
                    'market_id',
                    'You cannot move this engineer into a region you do not manage.',
                );
            }
        });
    }

    public function toDTO(): UpdateCustomerEngineerDTO
    {
        $marketId = $this->validated('market_id');
        $firstName = $this->validated('first_name');
        $lastName = $this->validated('last_name');
        $email = $this->validated('email');
        $password = $this->validated('password');
        $phone = $this->validated('phone');
        $title = $this->validated('title');
        $company = $this->validated('company');
        $isDefault = $this->validated('is_default');
        $isActive = $this->validated('is_active');

        return new UpdateCustomerEngineerDTO(
            marketId: is_numeric($marketId) ? (int) $marketId : null,
            firstName: is_string($firstName) ? $firstName : null,
            lastName: is_string($lastName) ? $lastName : null,
            email: is_string($email) ? $email : null,
            phone: is_string($phone) ? $phone : null,
            title: is_string($title) ? $title : null,
            company: is_string($company) && $company !== '' ? $company : null,
            password: is_string($password) && $password !== '' ? $password : null,
            isDefault: is_bool($isDefault) ? $isDefault : null,
            isActive: is_bool($isActive) ? $isActive : null,
            profilePhoto: $this->file('profile_photo'),
        );
    }
}
