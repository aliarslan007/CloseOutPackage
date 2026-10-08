<?php

declare(strict_types=1);

namespace App\Domains\Projects\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreProjectDocumentRequest extends FormRequest
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
            'document' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt', 'max:25600'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document.required' => 'Please choose the document to upload.',
            'document.mimes' => 'The document must be a PDF, Word, Excel, PowerPoint, or text file.',
            'document.max' => 'The document must be 25 MB or smaller.',
        ];
    }
}
