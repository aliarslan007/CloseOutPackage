<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Requests;

use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\ScopeOfWork\Rules\NotReservedImageGroupName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreSowImageGroupRequest extends FormRequest
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
        /** @var SowReport $report */
        $report = $this->route('report');

        return [
            'name' => [
                'required', 'string', 'max:120',
                new NotReservedImageGroupName,
                // Names double as PDF headings, so they must be unique per report.
                Rule::unique('sow_image_groups', 'name')->where('sow_report_id', $report->id),
            ],
        ];
    }
}
