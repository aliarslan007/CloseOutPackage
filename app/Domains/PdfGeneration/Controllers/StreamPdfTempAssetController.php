<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StreamPdfTempAssetController extends Controller
{
    public function __invoke(Request $request, string $batch, string $file): BinaryFileResponse
    {
        $cleanBatch = basename($batch);
        $cleanFile = basename($file);

        $path = storage_path("app/temp-pdf-assets/{$cleanBatch}/{$cleanFile}");

        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'no-cache, private',
        ]);
    }
}
