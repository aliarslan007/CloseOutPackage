<?php

declare(strict_types=1);

namespace App\Domains\Users\Requests;

use App\Domains\Users\DTOs\ChangeUserPasswordDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ChangeUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('changePassword', $this->route('user'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function toDTO(): ChangeUserPasswordDTO
    {
        /** @var string $password */
        $password = $this->validated('password');

        return new ChangeUserPasswordDTO(password: $password);
    }
}
