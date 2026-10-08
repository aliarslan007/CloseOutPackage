<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Controllers;

use App\Domains\CloseoutPackages\Actions\ResubmitPackageAction;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class ResubmitPackageController extends Controller
{
    public function __construct(private readonly ResubmitPackageAction $resubmitAction) {}

    public function __invoke(CloseoutPackage $package): RedirectResponse
    {
        $this->authorize('resubmit', $package);

        $this->resubmitAction->execute($package);

        return redirect()->back()->with('success', 'Package resubmitted for review.');
    }
}
