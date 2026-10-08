<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Requests;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\PdfGeneration\Models\PackageRevision;
use App\Domains\Photos\Models\Photo;
use App\Shared\Traits\DecodesObfuscatedKeys;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GeneratePartialPdfRequest extends FormRequest
{
    use DecodesObfuscatedKeys;

    public function authorize(): bool
    {
        return Gate::allows('generatePartial', [PackageRevision::class, $this->route('package')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'closeout_package_id' => ['required', 'integer', Rule::exists('closeout_packages', 'id')],
            'email_to' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var CloseoutPackage $package */
            $package = $this->route('package');

            $hasPhotos = Photo::query()
                ->where('closeout_package_id', $package->id)
                ->exists();

            if (! $hasPhotos) {
                $validator->errors()->add('closeout_package_id', 'At least one stage must have uploaded photos.');
            }
        });
    }
}
