<?php

declare(strict_types=1);

namespace App\Domains\CustomerPortal\Controllers;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Queries\PackageQuery;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\CustomerPortal\Services\CustomerPortalScopeService;
use App\Domains\PdfGeneration\Services\PackagePdfFilename;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomerPortalPdfController extends Controller
{
    public function __construct(
        private readonly CustomerPortalScopeService $scopeService,
        private readonly PackageQuery $packageQuery,
    ) {}

    public function __invoke(Request $request, CloseoutPackage $package): BinaryFileResponse|RedirectResponse
    {
        $this->scopeService->ensurePackageAccessible($this->user(), $package);
        $this->authorize('downloadPdf', [CustomerProjectAccess::class, $package]);

        $revision = $this->packageQuery->latestFinalRevision($package);

        if ($revision === null || $revision->storage_path === null) {
            return redirect()->back()->with('info', 'PDF not yet available.');
        }

        $path = $revision->storage_path;

        if (! Storage::disk('private')->exists($path)) {
            return redirect()->back()->with('info', 'PDF not yet available.');
        }

        return response()->file(
            Storage::disk('private')->path($path),
            PackagePdfFilename::downloadHeaders($package, $revision),
        );
    }
}
