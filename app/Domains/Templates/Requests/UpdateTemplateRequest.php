<?php

declare(strict_types=1);

namespace App\Domains\Templates\Requests;

use App\Domains\Templates\DTOs\UpdateTemplateDTO;
use App\Domains\Templates\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateTemplateRequest extends FormRequest
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
        /** @var Template $template */
        $template = $this->route('template');
        $newMarketId = $this->integer('market_id');
        $marketId = $template->marketIsMissing() && $newMarketId > 0 ? $newMarketId : $template->market_id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('templates', 'name')
                    ->where('market_id', $marketId)
                    ->withoutTrashed()
                    ->ignore($template->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'market_id' => ['sometimes', 'integer', Rule::exists('markets', 'id')->withoutTrashed()],
        ];
    }

    public function toDTO(): UpdateTemplateDTO
    {
        $name = $this->validated('name');
        $description = $this->validated('description');
        $isActive = $this->validated('is_active');
        $marketId = $this->validated('market_id');

        return new UpdateTemplateDTO(
            name: is_string($name) ? $name : null,
            description: is_string($description) ? $description : null,
            isActive: is_bool($isActive) ? $isActive : null,
            marketId: is_numeric($marketId) ? (int) $marketId : null,
        );
    }
}
