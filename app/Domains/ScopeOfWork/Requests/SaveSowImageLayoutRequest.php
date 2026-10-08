<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Requests;

use App\Domains\ScopeOfWork\Models\SowReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveSowImageLayoutRequest extends FormRequest
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
            'groups' => ['present', 'array'],
            'groups.*.id' => ['required', 'integer'],
            'groups.*.sort_order' => ['required', 'integer', 'min:0'],

            'images' => ['present', 'array'],
            'images.*.id' => ['required', 'integer'],
            'images.*.sort_order' => ['required', 'integer', 'min:0'],
            // null = ungrouped; ownership of the id is enforced in the action.
            'images.*.sow_image_group_id' => ['nullable', 'integer'],
        ];
    }
}
