<?php

declare(strict_types=1);

namespace App\Domains\Templates\Requests;

use App\Domains\Templates\DTOs\CreateTemplatePictureCategoryDTO;
use App\Domains\Templates\Models\TemplatePictureCategory;
use App\Domains\Templates\Models\TemplateSection;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreTemplatePictureCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('template'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // A stage must not contain the same photo group name twice (case-insensitive).
        // On update the category itself is excluded from the check.
        $duplicateInStage = function (string $attribute, mixed $value, Closure $fail): void {
            /** @var TemplateSection|null $section */
            $section = $this->route('section');

            if (! $section) {
                return;
            }

            /** @var TemplatePictureCategory|null $category */
            $category = $this->route('category');

            $exists = $section->categories()
                ->whereKeyNot($category?->getKey() ?? 0)
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim((string) $value))])
                ->exists();

            if ($exists) {
                $fail('This stage already has a photo group with this name.');
            }
        };

        return [
            'name' => ['required', 'string', 'max:100', $duplicateInStage],
            'description' => ['nullable', 'string', 'max:500'],
            'default_picture_count' => ['required', 'integer', 'min:1', 'max:99'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_required' => ['sometimes', 'boolean'],
        ];
    }

    public function toDTO(int $templateSectionId): CreateTemplatePictureCategoryDTO
    {
        /** @var string $name */
        $name = $this->validated('name');
        $description = $this->validated('description');
        /** @var int $defaultPictureCount */
        $defaultPictureCount = $this->validated('default_picture_count');
        /** @var int $sortOrder */
        $sortOrder = $this->validated('sort_order');

        return new CreateTemplatePictureCategoryDTO(
            templateSectionId: $templateSectionId,
            name: $name,
            description: is_string($description) ? $description : null,
            defaultPictureCount: (int) $defaultPictureCount,
            sortOrder: (int) $sortOrder,
            isRequired: (bool) $this->validated('is_required', false),
        );
    }
}
