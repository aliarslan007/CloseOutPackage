<?php

declare(strict_types=1);

namespace App\Domains\Markets\Requests;

use App\Domains\Markets\DTOs\UpdateMarketDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateMarketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('market'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('markets', 'name')->withoutTrashed()->ignore($this->route('market')),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function toDTO(): UpdateMarketDTO
    {
        $name = $this->validated('name');
        $description = $this->validated('description');
        $isActive = $this->validated('is_active');

        return new UpdateMarketDTO(
            name: is_string($name) ? $name : null,
            description: is_string($description) ? $description : null,
            isActive: is_bool($isActive) ? $isActive : null,
        );
    }
}
