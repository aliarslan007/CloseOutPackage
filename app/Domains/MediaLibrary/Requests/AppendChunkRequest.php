<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Requests;

use App\Domains\MediaLibrary\Services\MediaLibraryConfigService;
use Illuminate\Foundation\Http\FormRequest;

class AppendChunkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Ownership via the policy; the upload token is verified in the action.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $config = app(MediaLibraryConfigService::class);

        return [
            'chunk' => ['required', 'file', 'max:'.$config->chunkSizeKb()],
            // 9999 chunks is far beyond what the max file size allows; the
            // action enforces the real per-asset bound (total_chunks - 1).
            'chunk_index' => ['required', 'integer', 'min:0', 'max:9999'],
            'total_chunks' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ];
    }
}
