<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Requests;

use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignMediaAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Ownership runs via Gate::authorize('assign') in the action.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $categoryNotDeleted = function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_numeric($value)) {
                return;
            }

            $selection = PackageCategorySelection::query()
                ->with('templatePictureCategory')
                ->find((int) $value);

            if ($selection?->templatePictureCategory?->trashed()) {
                $fail('This photo category was removed from the template by an administrator. Please refresh your page.');
            }
        };

        return [
            'media_asset_id' => ['required_without:media_asset_ids', 'integer', Rule::exists('media_assets', 'id')],
            'media_asset_ids' => ['required_without:media_asset_id', 'array'],
            'media_asset_ids.*' => ['integer', Rule::exists('media_assets', 'id')],
            'category_selection_id' => [
                'required',
                'integer',
                Rule::exists('package_category_selections', 'id'),
                $categoryNotDeleted,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_selection_id.required' => 'Please select a photo category.',
            'category_selection_id.exists' => 'This photo category was removed from the template by an administrator. Please refresh your page.',
        ];
    }
}
