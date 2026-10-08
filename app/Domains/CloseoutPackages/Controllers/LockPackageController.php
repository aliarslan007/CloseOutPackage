<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Controllers;

use App\Domains\CloseoutPackages\Actions\LockPackageAction;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class LockPackageController extends Controller
{
    public function __construct(private readonly LockPackageAction $lockAction) {}

    public function __invoke(CloseoutPackage $package): RedirectResponse
    {
        $this->authorize('lock', $package);

        $this->lockAction->execute($package, $this->user());

        return redirect()->back()->with('success', 'Package locked.');
    }
}
