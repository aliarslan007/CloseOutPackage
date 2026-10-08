<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Requests;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RejectPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('reject', [CloseoutPackage::class, $this->route('package')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function reason(): string
    {
        $reason = $this->validated('reason');

        return is_string($reason) ? $reason : '';
    }
}
