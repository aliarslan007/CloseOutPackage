<?php

declare(strict_types=1);

namespace App\Domains\Users\Requests;

use App\Domains\Users\DTOs\UpdateUserDTO;
use App\Domains\Users\Models\User;
use App\Domains\Users\Requests\Concerns\ValidatesProfileFields;
use App\Shared\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        /** @var User $target */
        $target = $this->route('user');

        return array_merge($this->profileFieldRules($target), [
            // A customer user keeps its role here (edit name/contact), but the
            // Users module can never promote into or demote out of the customer
            // role — customer accounts live under Customer Account Settings.
            'role' => [
                'required',
                Rule::in($target->hasRole(Role::CUSTOMER->value)
                    ? [Role::CUSTOMER->value]
                    : Role::internalValues()),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => 'Customer accounts are managed in App Settings under Customer Account Settings.',
        ];
    }

    public function toDTO(): UpdateUserDTO
    {
        /** @var string $firstName */
        $firstName = $this->validated('first_name');
        /** @var string $lastName */
        $lastName = $this->validated('last_name');
        /** @var string $email */
        $email = $this->validated('email');
        /** @var string $role */
        $role = $this->validated('role');
        $isActive = $this->validated('is_active', true);

        $profilePhoto = $this->file('profile_photo');

        return new UpdateUserDTO(
            firstName: $firstName,
            lastName: $lastName,
            email: $email,
            role: $role,
            isActive: is_bool($isActive) ? $isActive : true,
            company: $this->nullableProfileField('company'),
            phone: $this->nullableProfileField('phone'),
            profilePhoto: $profilePhoto,
        );
    }
}
