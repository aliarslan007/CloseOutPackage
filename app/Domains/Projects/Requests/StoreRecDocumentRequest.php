<?php

declare(strict_types=1);

namespace App\Domains\Projects\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreRecDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('project'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document.required' => 'Please choose the REC document to upload.',
            'document.mimes' => 'The REC must be a PDF or an image (JPG, PNG, WebP) — the same file the customer company sent you.',
            'document.max' => 'The REC must be 20 MB or smaller. Scan it at a lower resolution if the export is too large.',
        ];
    }
}
