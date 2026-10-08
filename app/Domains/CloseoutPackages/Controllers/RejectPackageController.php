<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Controllers;

use App\Domains\CloseoutPackages\Actions\RejectPackageAction;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Requests\RejectPackageRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class RejectPackageController extends Controller
{
    public function __construct(private readonly RejectPackageAction $rejectPackageAction) {}

    public function __invoke(RejectPackageRequest $request, CloseoutPackage $package): RedirectResponse
    {
        $this->rejectPackageAction->execute($package, $request->reason());

        return redirect()->back()->with('success', 'Package rejected.');
    }
}
