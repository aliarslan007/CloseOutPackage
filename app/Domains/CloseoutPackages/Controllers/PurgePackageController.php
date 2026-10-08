<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Controllers;

use App\Domains\CloseoutPackages\Actions\DeleteGarbagePackageAction;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class PurgePackageController extends Controller
{
    public function __construct(private readonly DeleteGarbagePackageAction $deleteGarbagePackageAction) {}

    public function __invoke(CloseoutPackage $package): RedirectResponse
    {
        if (! $package->trashed()) {
            return redirect()->back()->with('error', 'Only soft-deleted packages can be purged.');
        }

        $this->deleteGarbagePackageAction->execute($package);

        return redirect()->route('admin.packages.deleted')->with('success', 'Package permanently deleted.');
    }
}
