<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Controllers;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\PdfGeneration\Models\PackageRevision;
use App\Domains\PdfGeneration\Services\PackagePdfFilename;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadRevisionPdfController extends Controller
{
    public function __invoke(CloseoutPackage $package, PackageRevision $revision): BinaryFileResponse
    {
        abort_unless($revision->closeout_package_id === $package->id, 404);

        $this->authorize('download', [PackageRevision::class, $package]);

        $path = $revision->storage_path;

        abort_unless(is_string($path) && $path !== '' && Storage::disk('private')->exists($path), 404);

        return response()->file(
            Storage::disk('private')->path($path),
            PackagePdfFilename::downloadHeaders($package, $revision),
        );
    }
}
