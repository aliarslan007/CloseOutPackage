<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Controllers;

use App\Domains\CloseoutPackages\Actions\UnlockPackageAction;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class UnlockPackageController extends Controller
{
    public function __construct(private readonly UnlockPackageAction $unlockAction) {}

    public function __invoke(CloseoutPackage $package): RedirectResponse
    {
        $this->authorize('unlock', $package);

        $this->unlockAction->execute($package, $this->user());

        return redirect()->back()->with('success', 'Package unlocked.');
    }
}
