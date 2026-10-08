<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Controllers;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Queries\PackageQuery;
use App\Domains\PdfGeneration\Models\PackageRevision;
use App\Domains\PdfGeneration\Services\PackagePdfFilename;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadPdfController extends Controller
{
    public function __construct(private readonly PackageQuery $packageQuery) {}

    public function __invoke(CloseoutPackage $package): BinaryFileResponse
    {
        $this->authorize('download', [PackageRevision::class, $package]);

        $revision = $this->packageQuery->latestFinalRevision($package);

        abort_unless($revision !== null, 404);

        return $this->serve($package, $revision);
    }

    private function serve(CloseoutPackage $package, PackageRevision $revision): BinaryFileResponse
    {
        $path = $revision->storage_path;

        abort_unless(is_string($path) && $path !== '' && Storage::disk('private')->exists($path), 404);

        return response()->file(
            Storage::disk('private')->path($path),
            PackagePdfFilename::downloadHeaders($package, $revision),
        );
    }
}
