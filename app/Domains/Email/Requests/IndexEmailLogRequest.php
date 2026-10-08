<?php

declare(strict_types=1);

namespace App\Domains\Email\Requests;

use App\Domains\Email\Models\EmailLog;
use App\Domains\Email\Queries\EmailLogQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IndexEmailLogRequest extends FormRequest
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
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'sort' => ['nullable', 'string', Rule::in(EmailLogQuery::sortKeys())],
        ];
    }
}
