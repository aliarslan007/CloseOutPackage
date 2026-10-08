<?php

declare(strict_types=1);

namespace App\Domains\Templates\Requests;

use App\Domains\Templates\DTOs\SaveFavoriteTemplateDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFavoriteTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'template_id' => ['required', 'integer', Rule::exists('templates', 'id')->where('is_active', 1)],
            'snapshot' => ['required', 'array'],
        ];
    }

    public function toDTO(int $userId): SaveFavoriteTemplateDTO
    {
        /** @var int $templateId */
        $templateId = $this->validated('template_id');
        /** @var string $name */
        $name = $this->validated('name');
        /** @var array<int, array<string, mixed>> $snapshot */
        $snapshot = $this->validated('snapshot');

        return new SaveFavoriteTemplateDTO(
            userId: $userId,
            templateId: $templateId,
            name: $name,
            snapshot: $snapshot,
        );
    }
}
