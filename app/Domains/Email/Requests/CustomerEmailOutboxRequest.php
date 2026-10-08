<?php

declare(strict_types=1);

namespace App\Domains\Email\Requests;

use App\Domains\Email\Enums\EmailStatus;
use App\Domains\Email\Enums\EmailType;
use App\Domains\Email\Models\EmailLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CustomerEmailOutboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', EmailLog::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(array_map(fn (EmailStatus $s): string => $s->value, EmailStatus::cases()))],
            'type' => ['nullable', 'string', Rule::in(array_map(fn (EmailType $t): string => $t->value, EmailType::manualTypes()))],
            'project_id' => ['nullable', Rule::exists('projects', 'id')],
        ];
    }
}
