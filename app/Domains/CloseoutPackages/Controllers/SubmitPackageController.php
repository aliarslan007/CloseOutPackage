<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Controllers;

use App\Domains\CloseoutPackages\Actions\SubmitPackageAction;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class SubmitPackageController extends Controller
{
    public function __construct(private readonly SubmitPackageAction $submitPackageAction) {}

    public function __invoke(CloseoutPackage $package): RedirectResponse
    {
        $this->authorize('submitPackage', $package);

        $this->submitPackageAction->execute($package, $this->user());

        return redirect()->back()->with('success', 'Package submitted.');
    }
}
