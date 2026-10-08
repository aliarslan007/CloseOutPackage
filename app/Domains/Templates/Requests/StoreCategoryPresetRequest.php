<?php

declare(strict_types=1);

namespace App\Domains\Templates\Requests;

use App\Domains\Templates\DTOs\CreateCategoryPresetDTO;
use App\Domains\Templates\Models\CategoryPreset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreCategoryPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', CategoryPreset::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'default_picture_count' => ['required', 'integer', 'min:1', 'max:99'],
            'is_required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function toDTO(): CreateCategoryPresetDTO
    {
        /** @var string $name */
        $name = $this->validated('name');
        $description = $this->validated('description');
        /** @var int $count */
        $count = (int) $this->validated('default_picture_count');

        return new CreateCategoryPresetDTO(
            name: $name,
            description: is_string($description) ? $description : null,
            defaultPictureCount: $count,
            isRequired: (bool) $this->validated('is_required', true),
            isActive: (bool) $this->validated('is_active', true),
        );
    }
}
