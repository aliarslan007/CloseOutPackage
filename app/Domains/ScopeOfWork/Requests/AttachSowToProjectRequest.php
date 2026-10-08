<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Requests;

use App\Domains\ScopeOfWork\Models\SowReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AttachSowToProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SowReport $report */
        $report = $this->route('report');

        return Gate::allows('attach', $report);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
        ];
    }
}
