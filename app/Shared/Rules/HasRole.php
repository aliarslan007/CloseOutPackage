<?php

declare(strict_types=1);

namespace App\Shared\Rules;

use App\Domains\Users\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class HasRole implements ValidationRule
{
    public function __construct(private readonly string $role) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = is_int($value) ? User::find($value) : null;

        if ($user === null || ! $user->hasRole($this->role)) {
            $fail("The selected user does not have the {$this->role} role.");
        }
    }
}
