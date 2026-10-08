<?php

declare(strict_types=1);

namespace App\Domains\Templates\Requests;

use App\Domains\Templates\DTOs\ReorderCategoriesDTO;
use App\Domains\Templates\Models\TemplateSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReorderCategoriesRequest extends FormRequest
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
        /** @var TemplateSection $section */
        $section = $this->route('section');

        return [
            'items' => ['required', 'array', 'max:200'],
            'items.*.id' => [
                'required',
                'integer',
                Rule::exists('template_picture_categories', 'id')
                    ->where('template_section_id', $section->id)
                    ->whereNull('deleted_at'),
            ],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    public function toDTO(): ReorderCategoriesDTO
    {
        /** @var list<array{id: int, sort_order: int}> $items */
        $items = $this->validated('items');

        return new ReorderCategoriesDTO($items);
    }
}
