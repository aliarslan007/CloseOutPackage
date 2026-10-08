<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Controllers;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\PdfGeneration\Services\PackagePdfFilename;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SignedPdfDownloadController extends Controller
{
    public function __invoke(Request $request, CloseoutPackage $package): BinaryFileResponse
    {
        // Signed links bypass auth by design; a locked package must stop serving
        // files so previously-emailed links become inert.
        if ($package->isLocked()) {
            abort(410, 'This package has been locked and is no longer accessible via this link.');
        }

        $revision = $request->query('type') === 'partial'
            ? $package->latestPartialRevision()->first()
            : $package->latestFinalRevision()->first();

        $path = $revision?->storage_path;

        abort_unless(
            $revision !== null && is_string($path) && $path !== '' && Storage::disk('private')->exists($path),
            404,
        );

        return response()->file(
            Storage::disk('private')->path($path),
            PackagePdfFilename::downloadHeaders($package, $revision),
        );
    }
}
