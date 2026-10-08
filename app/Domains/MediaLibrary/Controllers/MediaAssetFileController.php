<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Controllers;

use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaAssetFileController extends Controller
{
    private const ALLOWED_CONVERSIONS = ['thumb', 'medium'];

    public function __invoke(Request $request, MediaAsset $asset): BinaryFileResponse
    {
        $this->authorize('view', $asset);

        $collection = $request->boolean('source') && $asset->hasMedia(MediaAsset::SOURCE_COLLECTION)
            ? MediaAsset::SOURCE_COLLECTION
            : MediaAsset::COLLECTION;

        $media = $asset->getFirstMedia($collection);

        abort_unless($media !== null, 404);

        $conversion = $request->query('conversion');

        if (is_string($conversion) && in_array($conversion, self::ALLOWED_CONVERSIONS, true)) {
            $conversionPath = $media->getPath($conversion);

            if ($media->hasGeneratedConversion($conversion) && file_exists($conversionPath)) {
                return response()->file($conversionPath);
            }
        }

        $originalPath = $media->getPath();
        abort_unless(file_exists($originalPath), 404);

        return response()->file($originalPath, [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
        ]);
    }
}
