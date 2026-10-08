<?php

declare(strict_types=1);

namespace App\Domains\EmailTemplates\Requests;

use App\Domains\Email\Enums\EmailType;
use App\Domains\EmailTemplates\DTOs\CreateEmailTemplateDTO;
use App\Domains\EmailTemplates\Enums\EmailTemplateStatus;
use App\Domains\EmailTemplates\Models\EmailTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', EmailTemplate::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email_type' => ['required', Rule::in(array_column(EmailType::cases(), 'value'))],
            'name' => ['required', 'string', 'max:255'],
            'subject_template' => ['required', 'string', 'max:500'],
            'body_template' => ['required', 'string', 'max:10000'],
            'status' => ['sometimes', Rule::in(array_column(EmailTemplateStatus::cases(), 'value'))],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    public function toDTO(): CreateEmailTemplateDTO
    {
        $status = $this->validated('status');

        return new CreateEmailTemplateDTO(
            emailType: EmailType::from((string) $this->validated('email_type')),
            name: (string) $this->validated('name'),
            subjectTemplate: (string) $this->validated('subject_template'),
            bodyTemplate: (string) $this->validated('body_template'),
            status: EmailTemplateStatus::from(is_string($status) ? $status : EmailTemplateStatus::ACTIVE->value),
            isDefault: (bool) $this->validated('is_default', false),
        );
    }
}
