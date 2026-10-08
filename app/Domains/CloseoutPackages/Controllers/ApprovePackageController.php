<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Controllers;

use App\Domains\CloseoutPackages\Actions\ApprovePackageAction;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class ApprovePackageController extends Controller
{
    public function __construct(private readonly ApprovePackageAction $approvePackageAction) {}

    public function __invoke(CloseoutPackage $package): RedirectResponse
    {
        $this->authorize('approve', $package);

        $this->approvePackageAction->execute($package, $this->user());

        return redirect()->back()->with('success', 'Package approved.');
    }
}
