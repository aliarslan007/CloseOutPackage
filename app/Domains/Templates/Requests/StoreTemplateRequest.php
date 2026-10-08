<?php

declare(strict_types=1);

namespace App\Domains\Templates\Requests;

use App\Domains\Templates\DTOs\CreateTemplateDTO;
use App\Domains\Templates\Models\Template;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Template::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'market_id' => ['required', 'integer', 'exists:markets,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('templates', 'name')
                    ->where(fn (Builder $query) => $query->where('market_id', $this->input('market_id')))
                    ->withoutTrashed(),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function toDTO(): CreateTemplateDTO
    {
        /** @var int $marketId */
        $marketId = $this->validated('market_id');
        /** @var string $name */
        $name = $this->validated('name');
        $description = $this->validated('description');
        $isActive = $this->validated('is_active');

        return new CreateTemplateDTO(
            marketId: (int) $marketId,
            name: is_string($name) ? $name : '',
            description: is_string($description) ? $description : null,
            isActive: is_bool($isActive) ? $isActive : true,
        );
    }
}
