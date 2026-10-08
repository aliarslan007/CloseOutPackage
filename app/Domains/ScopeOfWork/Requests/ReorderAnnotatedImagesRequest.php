<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Requests;

use App\Domains\ScopeOfWork\Models\SowReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReorderAnnotatedImagesRequest extends FormRequest
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
            'order' => ['required', 'array', 'min:1'],
            'order.*.id' => ['required', 'integer'],
            'order.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
