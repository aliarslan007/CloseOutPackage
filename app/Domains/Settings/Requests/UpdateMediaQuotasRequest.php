<?php

declare(strict_types=1);

namespace App\Domains\Settings\Requests;

use App\Domains\Settings\Models\AppSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateMediaQuotasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', AppSetting::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'admin_quota_gb' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'manager_quota_gb' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'supervisor_quota_gb' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ];
    }
}
