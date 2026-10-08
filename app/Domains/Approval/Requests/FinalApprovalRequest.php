<?php

declare(strict_types=1);

namespace App\Domains\Approval\Requests;

use App\Domains\Approval\Models\PhotoApproval;
use App\Domains\Approval\Services\ApprovalReadinessService;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Shared\Traits\DecodesObfuscatedKeys;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class FinalApprovalRequest extends FormRequest
{
    use DecodesObfuscatedKeys;

    public function authorize(): bool
    {
        return Gate::allows('finalApprove', [PhotoApproval::class, $this->route('package')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'closeout_package_id' => ['required', 'integer', 'exists:closeout_packages,id'],
            'site_details' => ['nullable', 'array'],
            'site_details.*.source' => ['required', 'string', 'in:auth_center,manual'],
            'site_details.*.site_type' => ['required', 'string', 'in:hub,smallcell'],
            'site_details.*.name' => ['required', 'string', 'max:255'],
            'site_details.*.auth_site_id' => ['nullable', 'integer'],
            'site_details.*.region' => ['nullable', 'string', 'max:100'],
            'site_details.*.state' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var CloseoutPackage $package */
            $package = $this->route('package');

            $report = app(ApprovalReadinessService::class)->check($package);

            if ($report->isReady) {
                return;
            }

            foreach ($report->failures as $failure) {
                $validator->errors()->add('readiness_failures', $failure->message);
            }
        });
    }
}
