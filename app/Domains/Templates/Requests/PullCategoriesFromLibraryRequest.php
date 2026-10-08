<?php

declare(strict_types=1);

namespace App\Domains\Templates\Requests;

use App\Domains\Templates\Models\CategoryPreset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PullCategoriesFromLibraryRequest extends FormRequest
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
            'preset_ids' => ['required', 'array', 'min:1', 'max:50'],
            'preset_ids.*' => [
                'required',
                'integer',
                Rule::exists(CategoryPreset::class, 'id')->where(
                    fn ($query) => $query->where('is_active', true),
                ),
            ],
        ];
    }

    /**
     * @return list<int>
     */
    public function presetIds(): array
    {
        /** @var list<int> $ids */
        $ids = $this->validated('preset_ids');

        return array_values(array_unique(array_map(intval(...), $ids)));
    }
}
