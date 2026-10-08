<?php

declare(strict_types=1);

namespace App\Domains\Users\Requests\Concerns;

use App\Domains\Users\Models\User;
use Illuminate\Validation\Rule;

/**
 * Shared validation rules for the user profile fields (first/last name,
 * email, company, phone and profile photo). Used by the admin store/update
 * requests and by the self-service profile update request so the rules
 * never drift apart.
 */
trait ValidatesProfileFields
{
    /**
     * @param  User|int|null  $ignoreUser  User (or id) to ignore for the
     *                                     unique email rule, or null when
     *                                     creating a brand new user.
     * @return array<string, mixed>
     */
    protected function profileFieldRules(User|int|null $ignoreUser = null): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($ignoreUser),
            ],
            'company' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^(\+\d{1,2}\s?)?(\(\d{3}\)|\d{3})[\s.\-]?\d{3}[\s.\-]?\d{4}$/'],
            'profile_photo' => $this->profilePhotoRule(),
        ];
    }

    /**
     * Shared photo rule so the admin user form and the customer-engineer
     * dialog can never drift apart on type or size limits.
     *
     * @return array<int, string>
     */
    protected function profilePhotoRule(): array
    {
        return ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'];
    }

    /**
     * Normalize an optional value to null when it is empty, so the database
     * never stores whitespace-only values. Shared by the form requests and
     * the profile controller.
     */
    public static function normalizeOptionalValue(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * Normalize an optional validated field to null when it is empty.
     */
    private function nullableProfileField(string $key): ?string
    {
        return self::normalizeOptionalValue($this->validated($key));
    }
}
