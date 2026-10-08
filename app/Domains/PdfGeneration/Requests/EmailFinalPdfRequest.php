<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Requests;

use App\Domains\PdfGeneration\Models\PackageRevision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class EmailFinalPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('emailFinalPdf', [PackageRevision::class, $this->route('package')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email_to' => ['nullable', 'email', 'max:255'],
            'email_template_id' => ['nullable', 'integer', 'exists:email_templates,id'],
            'subject_override' => ['nullable', 'string', 'max:500'],
            'body_override' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
