<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Requests;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class AttachPackageNoteLibraryImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $package = $this->route('package');

        return $package instanceof CloseoutPackage
            && Gate::allows('updateSiteNotes', $package);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'media_asset_ids' => ['required', 'array', 'min:1', 'max:'.CloseoutPackage::PACKAGE_SITE_NOTES_IMAGE_LIMIT],
            'media_asset_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'media_asset_ids.required' => 'Select at least one image from the library.',
            'media_asset_ids.min' => 'Select at least one image from the library.',
            'media_asset_ids.max' => 'You can add up to '.CloseoutPackage::PACKAGE_SITE_NOTES_IMAGE_LIMIT.' images in one go.',
            'media_asset_ids.*.distinct' => 'One of the selected images was chosen twice. Please review your selection.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $package = $this->route('package');

            if (! $package instanceof CloseoutPackage) {
                return;
            }

            $incoming = is_array($this->input('media_asset_ids')) ? count($this->input('media_asset_ids')) : 0;
            $attached = $package->getMedia(CloseoutPackage::PACKAGE_SITE_NOTES_COLLECTION)->count();
            $limit = CloseoutPackage::PACKAGE_SITE_NOTES_IMAGE_LIMIT;

            if ($attached + $incoming > $limit) {
                $remaining = max(0, $limit - $attached);
                $v->errors()->add(
                    'media_asset_ids',
                    $remaining === 0
                        ? "These notes can hold {$limit} images in total, and they are all filled. Remove one before adding another."
                        : "These notes can hold {$limit} images in total — only {$remaining} more can be added. Please remove some and try again.",
                );
            }
        });
    }
}
