<?php

declare(strict_types=1);

namespace App\Domains\Templates\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateChecklistItemRequest extends FormRequest
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
        ];
    }
}
