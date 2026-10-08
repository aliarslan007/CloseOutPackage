<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Controllers;

use App\Domains\CloseoutPackages\Actions\UpdatePackageChecklistAction;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Requests\UpdatePackageChecklistRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class PackageChecklistController extends Controller
{
    public function __construct(private readonly UpdatePackageChecklistAction $updateChecklistAction) {}

    public function update(UpdatePackageChecklistRequest $request, CloseoutPackage $package): RedirectResponse
    {
        $this->updateChecklistAction->execute($package, $request->toDTO());

        return redirect()->back()->with('success', 'Checklist updated.');
    }
}
