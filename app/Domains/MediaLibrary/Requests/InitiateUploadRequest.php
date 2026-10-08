<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Requests;

use App\Domains\MediaLibrary\Services\MediaLibraryConfigService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiateUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Role/project checks run via the policy in the controller.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $config = app(MediaLibraryConfigService::class);

        return [
            'filename' => ['required', 'string', 'max:500'],
            'total_size' => ['required', 'integer', 'min:1', 'max:'.$config->maxFileBytes()],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:'.$this->maxChunkCount($config)],
            'mime_type' => ['required', 'string', Rule::in($config->allowedMimes())],
            'captured_at' => ['nullable', 'date'],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'market_id' => ['nullable', 'integer', Rule::exists('markets', 'id')],
        ];
    }

    private function maxChunkCount(MediaLibraryConfigService $config): int
    {
        return (int) ceil($config->maxFileBytes() / max(1, $config->chunkSizeBytes())) + 1;
    }
}
