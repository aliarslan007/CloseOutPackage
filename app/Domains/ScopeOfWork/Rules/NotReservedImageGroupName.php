<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Rules;

use App\Domains\ScopeOfWork\Models\SowImageGroup;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Blocks a user-named group from claiming the PDF's ungrouped-fallback heading,
 * which would print two identical section headings.
 */
final class NotReservedImageGroupName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && mb_strtolower(trim($value)) === mb_strtolower(SowImageGroup::UNGROUPED_HEADING)) {
            $fail('The name "'.SowImageGroup::UNGROUPED_HEADING.'" is reserved for ungrouped photos.');
        }
    }
}
