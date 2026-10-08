<?php

declare(strict_types=1);

namespace App\Domains\Users\Requests;

use App\Domains\Users\DTOs\UpdateProfileDTO;
use App\Domains\Users\Requests\Concerns\ValidatesProfileFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class ProfileUpdateRequest extends FormRequest
{
    use ValidatesProfileFields;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->profileFieldRules($this->user());
    }

    public function toDTO(): UpdateProfileDTO
    {
        $photo = $this->file('profile_photo');

        return new UpdateProfileDTO(
            firstName: (string) $this->validated('first_name'),
            lastName: (string) $this->validated('last_name'),
            email: (string) $this->validated('email'),
            company: $this->nullableProfileField('company'),
            phone: $this->nullableProfileField('phone'),
            profilePhoto: $photo instanceof UploadedFile ? $photo : null,
        );
    }
}
