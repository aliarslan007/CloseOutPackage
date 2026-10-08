<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Requests;

use App\Domains\ScopeOfWork\Models\SowReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SowReport $report */
        $report = $this->route('report');

        return Gate::allows('update', $report);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'responses' => ['present', 'array'],
            'responses.*.sow_checklist_item_id' => ['required', 'integer', Rule::exists('sow_checklist_items', 'id')],
            'responses.*.response' => ['nullable', 'string', Rule::in(['yes', 'no', 'n_a'])],
            'responses.*.explanation' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
