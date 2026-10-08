<?php

declare(strict_types=1);

namespace App\Domains\EmailTemplates\Requests;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Email\Enums\EmailType;
use App\Domains\EmailTemplates\Models\EmailTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PreviewEmailTemplateRequest extends FormRequest
{
    /**
     * Template preview is available to anyone who may read the library
     * (admins in the editor, managers in the send-flow modal). A referenced
     * package must additionally be viewable by the requester.
     */
    public function authorize(): bool
    {
        if (! Gate::allows('viewAny', EmailTemplate::class)) {
            return false;
        }

        $packageId = $this->integer('package_id');

        return $packageId === 0 || $this->user()->can('view', CloseoutPackage::query()->findOrFail($packageId));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email_type' => ['required', Rule::in(array_column(EmailType::cases(), 'value'))],
            'subject_template' => ['required', 'string', 'max:500'],
            'body_template' => ['required', 'string', 'max:10000'],
            'package_id' => ['nullable', 'integer', 'exists:closeout_packages,id'],
        ];
    }
}
