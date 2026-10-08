<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The library's editable metadata is deliberately narrow: re-tagging an asset
 * onto a project (or clearing the tag) for organizational grouping. File,
 * MIME, size and capture data are immutable once the upload is processed.
 */
class UpdateMediaAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Ownership runs via the policy in the action.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
        ];
    }
}
