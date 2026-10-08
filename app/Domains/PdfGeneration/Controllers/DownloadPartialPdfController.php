<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Controllers;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\PdfGeneration\Models\PackageRevision;
use App\Domains\PdfGeneration\Services\PackagePdfFilename;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadPartialPdfController extends Controller
{
    public function __invoke(CloseoutPackage $package): BinaryFileResponse
    {
        $this->authorize('download', [PackageRevision::class, $package]);

        $revision = $package->latestPartialRevision()
            ->whereNotNull('storage_path')
            ->first();

        abort_unless($revision !== null, 404);

        $path = $revision->storage_path;

        abort_unless(is_string($path) && $path !== '' && Storage::disk('private')->exists($path), 404);

        return response()->file(
            Storage::disk('private')->path($path),
            PackagePdfFilename::downloadHeaders($package, $revision),
        );
    }
}
