<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Requests;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\PdfGeneration\Models\PackageRevision;
use App\Shared\Traits\DecodesObfuscatedKeys;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SendProgressPdfEmailRequest extends FormRequest
{
    use DecodesObfuscatedKeys;

    public function authorize(): bool
    {
        return Gate::allows('emailProgressPdf', [PackageRevision::class, $this->route('package')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CloseoutPackage $package */
        $package = $this->route('package');

        return [
            'email_to' => ['required', 'email', 'max:255'],
            'closeout_package_id' => [
                'required',
                'integer',
                Rule::exists('closeout_packages', 'id')->where('id', $package->id),
            ],
            'email_template_id' => ['nullable', 'integer', 'exists:email_templates,id'],
            'subject_override' => ['nullable', 'string', 'max:500'],
            'body_override' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
