<?php

declare(strict_types=1);

namespace App\Domains\Markets\Requests;

use App\Domains\Markets\DTOs\CreateMarketDTO;
use App\Domains\Markets\Models\Market;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreMarketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Market::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('markets', 'name')->withoutTrashed()],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function toDTO(): CreateMarketDTO
    {
        /** @var string $name */
        $name = $this->validated('name');
        $description = $this->validated('description');
        $isActive = $this->validated('is_active', true);

        return new CreateMarketDTO(
            name: $name,
            description: is_string($description) ? $description : null,
            isActive: is_bool($isActive) ? $isActive : true,
        );
    }
}
