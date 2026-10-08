<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Requests;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class StorePackageNoteImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $package = $this->route('package');

        if (! $package instanceof CloseoutPackage) {
            return false;
        }

        return Gate::allows('updateSiteNotes', $package);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $limit = CloseoutPackage::PACKAGE_SITE_NOTES_IMAGE_LIMIT;

        return [
            'images' => ['required', 'array', 'min:1', 'max:'.$limit],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,heic', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        // Human phrasing for every machine-default rule. The 10240 KB cap is
        // also surfaced in the UI ("up to 10 MB each") so it rarely triggers.
        return [
            'images.required' => 'Choose at least one image to add.',
            'images.array' => 'Choose at least one image to add.',
            'images.min' => 'Choose at least one image to add.',
            'images.max' => 'You can add up to '.CloseoutPackage::PACKAGE_SITE_NOTES_IMAGE_LIMIT.' images in one go.',
            'images.*.image' => 'One of the selected files is not an image. Please use JPG, PNG, WebP or HEIC.',
            'images.*.mimes' => 'One of the images is not a supported type. Please use JPG, PNG, WebP or HEIC.',
            'images.*.max' => 'One of the images is larger than 10 MB. Please choose a smaller image.',
            'images.*.uploaded' => 'One of the images did not finish uploading. Please try again.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $package = $this->route('package');

            if (! $package instanceof CloseoutPackage) {
                return;
            }

            $incoming = count((array) $this->file('images', []));
            $attached = $package->getMedia(CloseoutPackage::PACKAGE_SITE_NOTES_COLLECTION)->count();
            $limit = CloseoutPackage::PACKAGE_SITE_NOTES_IMAGE_LIMIT;

            if ($attached + $incoming > $limit) {
                $remaining = max(0, $limit - $attached);
                $v->errors()->add(
                    'images',
                    $remaining === 0
                        ? "These notes can hold {$limit} images in total, and they are all filled. Remove one before adding another."
                        : "These notes can hold {$limit} images in total — only {$remaining} more can be added. Please remove some and try again.",
                );
            }
        });
    }
}
