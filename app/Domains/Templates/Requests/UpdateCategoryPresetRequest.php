<?php

declare(strict_types=1);

namespace App\Domains\Templates\Requests;

use App\Domains\Templates\DTOs\UpdateCategoryPresetDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateCategoryPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('category_preset'));
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

    public function toDTO(): UpdateCategoryPresetDTO
    {
        /** @var string $name */
        $name = $this->validated('name');
        $description = $this->validated('description');
        /** @var int $count */
        $count = (int) $this->validated('default_picture_count');

        return new UpdateCategoryPresetDTO(
            name: $name,
            description: is_string($description) ? $description : null,
            defaultPictureCount: $count,
            isRequired: (bool) $this->validated('is_required', true),
            isActive: (bool) $this->validated('is_active', true),
        );
    }
}
