<?php

declare(strict_types=1);

namespace App\Domains\Photos\Controllers;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Photos\Models\Photo;
use App\Domains\Photos\Queries\PhotoQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GetPhotoSignedUrlController extends Controller
{
    private const ALLOWED_CONVERSIONS = ['thumb', 'medium', 'pdf'];

    public function __construct(private readonly PhotoQuery $photoQuery) {}

    public function __invoke(Request $request, CloseoutPackage $package, Photo $photo): BinaryFileResponse
    {
        abort_unless($photo->closeout_package_id === $package->id, 404);

        $this->authorize('viewPhoto', $photo);

        $media = $this->photoQuery->media($photo);

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

        return response()->file($originalPath);
    }
}
