<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Controllers;

use App\Domains\CloseoutPackages\Actions\RestorePackageAction;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class RestorePackageController extends Controller
{
    public function __construct(private readonly RestorePackageAction $restorePackageAction) {}

    public function __invoke(CloseoutPackage $package): RedirectResponse
    {
        if (! $package->trashed()) {
            return redirect()->back()->with('error', 'Only soft-deleted packages can be restored.');
        }

        // The trash screen is not project-scoped, so a package whose project was
        // deleted is reachable here — restoring it would revive work with no home.
        if ($package->project === null || $package->project->trashed()) {
            return redirect()->back()->with('error', 'This package belongs to a deleted project and cannot be restored.');
        }

        $this->restorePackageAction->execute($package);

        return redirect()->route('admin.packages.deleted')->with('success', 'Package restored.');
    }
}
