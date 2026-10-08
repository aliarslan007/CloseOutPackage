<?php

declare(strict_types=1);

namespace App\Domains\Templates\Requests;

use App\Domains\Templates\DTOs\CreateChecklistItemDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreChecklistItemRequest extends FormRequest
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
        return [
            'label' => ['required', 'string', 'max:1000'],
            'has_explain_field' => ['sometimes', 'boolean'],
            'allows_na' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    public function toDTO(int $templateId): CreateChecklistItemDTO
    {
        /** @var string $label */
        $label = $this->validated('label');
        /** @var int $sortOrder */
        $sortOrder = $this->validated('sort_order');

        return new CreateChecklistItemDTO(
            templateId: $templateId,
            label: is_string($label) ? $label : '',
            hasExplainField: (bool) $this->validated('has_explain_field', false),
            allowsNa: (bool) $this->validated('allows_na', false),
            sortOrder: (int) $sortOrder,
        );
    }
}
