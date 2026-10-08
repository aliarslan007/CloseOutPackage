<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Requests;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\PdfGeneration\Models\PackageRevision;
use App\Shared\Traits\DecodesObfuscatedKeys;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class GenerateFinalPdfRequest extends FormRequest
{
    use DecodesObfuscatedKeys;

    public function authorize(): bool
    {
        return Gate::allows('generateFinal', [PackageRevision::class, $this->route('package')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'closeout_package_id' => [
                'required',
                'integer',
                Rule::exists('closeout_packages', 'id')->where('status', PackageStatus::APPROVED->value),
            ],
        ];
    }
}
