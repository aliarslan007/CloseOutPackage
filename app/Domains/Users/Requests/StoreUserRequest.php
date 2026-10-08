<?php

declare(strict_types=1);

namespace App\Domains\Users\Requests;

use App\Domains\Users\DTOs\CreateUserDTO;
use App\Domains\Users\Models\User;
use App\Domains\Users\Requests\Concerns\ValidatesProfileFields;
use App\Shared\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use ValidatesProfileFields;

    public function authorize(): bool
    {
        return Gate::allows('manage', User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge($this->profileFieldRules(), [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            // Customers (incl. customer engineers) are portal accounts — they are
            // created under App Settings → Customer Account Settings, never here.
            'role' => ['required', Rule::enum(Role::class), Rule::notIn([Role::CUSTOMER->value])],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.not_in' => 'Customer accounts are created in App Settings under Customer Account Settings.',
        ];
    }

    public function toDTO(): CreateUserDTO
    {
        /** @var string $firstName */
        $firstName = $this->validated('first_name');
        /** @var string $lastName */
        $lastName = $this->validated('last_name');
        /** @var string $email */
        $email = $this->validated('email');
        /** @var string $password */
        $password = $this->validated('password');
        /** @var string $role */
        $role = $this->validated('role');
        $isActive = $this->validated('is_active', true);

        $profilePhoto = $this->file('profile_photo');

        return new CreateUserDTO(
            firstName: $firstName,
            lastName: $lastName,
            email: $email,
            password: $password,
            role: $role,
            isActive: is_bool($isActive) ? $isActive : true,
            company: $this->nullableProfileField('company'),
            phone: $this->nullableProfileField('phone'),
            profilePhoto: $profilePhoto,
        );
    }
}
