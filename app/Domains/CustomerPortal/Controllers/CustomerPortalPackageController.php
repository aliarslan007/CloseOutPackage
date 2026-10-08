<?php

declare(strict_types=1);

namespace App\Domains\CustomerPortal\Controllers;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\CustomerPortal\Services\CustomerPortalScopeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class CustomerPortalPackageController extends Controller
{
    public function __construct(
        private readonly CustomerPortalScopeService $scopeService,
    ) {}

    public function __invoke(CloseoutPackage $package): RedirectResponse
    {
        $this->scopeService->ensurePackageAccessible($this->user(), $package);
        $this->authorize('viewPackage', [CustomerProjectAccess::class, $package]);

        // Use the obfuscated route keys (like the rest of the app) and land the
        // customer on the requested package so it opens directly in the project view.
        return redirect()->route('portal.projects.show', [
            'project' => $package->project,
            'package' => $package->getRouteKey(),
        ]);
    }
}
