<?php

declare(strict_types=1);

namespace App\Domains\Users\Requests;

use App\Domains\Users\DTOs\SsoUserSyncDTO;
use Illuminate\Foundation\Http\FormRequest;

class SsoUserSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['nullable', 'string'],
        ];
    }

    public function toDTO(): SsoUserSyncDTO
    {
        /** @var string $uuid */
        $uuid = $this->validated('uuid');
        /** @var string $firstName */
        $firstName = $this->validated('first_name');
        /** @var string $lastName */
        $lastName = $this->validated('last_name');
        /** @var string $email */
        $email = $this->validated('email');
        $password = $this->validated('password');

        return new SsoUserSyncDTO(
            uuid: $uuid,
            firstName: $firstName,
            lastName: $lastName,
            email: $email,
            password: is_string($password) && $password !== '' ? $password : null,
        );
    }
}
