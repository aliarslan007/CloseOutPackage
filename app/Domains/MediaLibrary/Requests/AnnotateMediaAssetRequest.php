<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnnotateMediaAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Checked via policy in controller
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->layer)) {
            $decoded = json_decode($this->layer, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['layer' => $decoded]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'max:20480'], // max 20MB
            'save_mode' => ['required', 'string', Rule::in(['copy', 'overwrite'])],
            'layer' => ['nullable', 'array'],
            'layer.base_width' => ['required_with:layer', 'integer', 'min:1'],
            'layer.nodes' => ['nullable', 'array'],
        ];
    }
}
