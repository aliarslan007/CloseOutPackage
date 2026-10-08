<?php

declare(strict_types=1);

namespace App\Domains\Photos\Rules;

use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\Photos\Models\Photo;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Blocks adding a photo to a bucket already at its admin-defined capacity,
 * while still allowing a re-upload at an existing (replaceable) position.
 */
final class CategoryCapacityNotExceeded implements ValidationRule
{
    public function __construct(
        private readonly ?PackageCategorySelection $selection,
        private readonly ?int $position,
        private readonly bool $isFeedbackRevision = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->isFeedbackRevision || $this->selection === null) {
            return;
        }

        $existingPhoto = $this->position !== null
            ? Photo::query()
                ->where('package_category_selection_id', $this->selection->id)
                ->where('position', $this->position)
                ->whereNull('replaced_at')
                ->first()
            : null;

        if ($existingPhoto !== null) {
            return;
        }

        $currentPhotoCount = Photo::query()
            ->where('package_category_selection_id', $this->selection->id)
            ->whereNull('replaced_at')
            ->count();

        if ($currentPhotoCount >= $this->selection->picture_count) {
            $fail('Bucket capacity is full. Remove an existing one to add a new one.');
        }
    }
}
