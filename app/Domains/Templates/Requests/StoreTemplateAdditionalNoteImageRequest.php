<?php

declare(strict_types=1);

namespace App\Domains\Templates\Requests;

use App\Domains\Templates\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class StoreTemplateAdditionalNoteImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('template'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:'.Template::ADDITIONAL_NOTES_IMAGE_LIMIT],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,heic', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $template = $this->route('template');

            if (! $template instanceof Template) {
                return;
            }

            $incoming = count((array) $this->file('images', []));
            $attached = $template->getMedia(Template::ADDITIONAL_NOTES_COLLECTION)->count();
            $limit = Template::ADDITIONAL_NOTES_IMAGE_LIMIT;

            if ($attached + $incoming > $limit) {
                $v->errors()->add(
                    'images',
                    "These notes can hold {$limit} images in total. Remove one before adding another.",
                );
            }
        });
    }
}
