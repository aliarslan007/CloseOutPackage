<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Requests;

use App\Domains\MediaLibrary\Enums\MediaPickerScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query-param filters shared by the library grid (GET /) and the COP picker
 * (GET /picker). The picker also carries `project_id` + `scope`; index simply
 * omits them and stays on the viewer's own assets.
 */
class ListMediaAssetsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'unassigned' => ['nullable', 'boolean'],
            'scope' => ['nullable', Rule::enum(MediaPickerScope::class)],
            'search' => ['nullable', 'string', 'max:255'],
            'market_id' => ['nullable', 'integer', Rule::exists('markets', 'id')],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', 'string', Rule::in(['all', 'ready', 'processing', 'failed'])],
            'sort' => ['nullable', 'string', Rule::in(['created_at', '-created_at', 'captured_at', '-captured_at', 'original_filename', '-original_filename', 'file_size_bytes', '-file_size_bytes'])],
        ];
    }
}
