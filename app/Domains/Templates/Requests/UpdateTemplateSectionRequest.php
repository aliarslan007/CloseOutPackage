<?php

declare(strict_types=1);

namespace App\Domains\Templates\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateTemplateSectionRequest extends FormRequest
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
            'heading' => ['required', 'string', 'max:500'],
            'admin_notes' => ['nullable', 'string'],
        ];
    }
}
