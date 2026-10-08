<?php

declare(strict_types=1);

namespace App\Domains\Photos\Rules;

use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a category whose template picture category was soft-deleted by an
 * administrator, so a stale form cannot upload into a bucket that no longer
 * exists.
 */
final class CategorySelectionNotDeleted implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            return;
        }

        $selection = PackageCategorySelection::query()
            ->with('templatePictureCategory')
            ->find((int) $value);

        if ($selection?->templatePictureCategory?->trashed()) {
            $fail('This photo category was removed from the template by an administrator. Please refresh your page.');
        }
    }
}
