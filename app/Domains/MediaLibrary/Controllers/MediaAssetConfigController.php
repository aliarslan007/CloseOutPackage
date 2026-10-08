<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Controllers;

use App\Domains\MediaLibrary\Services\MediaLibraryConfigService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Exposes config/cop.php media settings to the frontend so the client never
 * hardcodes the MIME allowlist or size limits. Values are read from the same
 * typed source (MediaLibraryConfigService) the server-side validation uses.
 */
class MediaAssetConfigController extends Controller
{
    public function __construct(private readonly MediaLibraryConfigService $config) {}

    public function __invoke(): JsonResponse
    {
        return response()->json([
            'allowed_mimes' => $this->config->allowedMimes(),
            'max_file_bytes' => $this->config->maxFileBytes(),
            'chunk_size_bytes' => $this->config->chunkSizeBytes(),
        ]);
    }
}
