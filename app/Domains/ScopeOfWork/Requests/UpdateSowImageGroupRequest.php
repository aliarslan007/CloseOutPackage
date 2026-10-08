<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Requests;

use App\Domains\ScopeOfWork\Models\SowImageGroup;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\ScopeOfWork\Rules\NotReservedImageGroupName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateSowImageGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SowReport $report */
        $report = $this->route('report');

        /** @var SowImageGroup $group */
        $group = $this->route('group');

        // Routes are unscoped, so a cross-report group id must be refused here.
        return $group->sow_report_id === $report->id && Gate::allows('update', $report);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var SowReport $report */
        $report = $this->route('report');

        /** @var SowImageGroup $group */
        $group = $this->route('group');

        return [
            'name' => [
                'required', 'string', 'max:120',
                new NotReservedImageGroupName,
                Rule::unique('sow_image_groups', 'name')
                    ->where('sow_report_id', $report->id)
                    ->ignore($group->id),
            ],
        ];
    }
}
