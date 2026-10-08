<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Controllers;

use App\Domains\CloseoutPackages\Actions\UpdateSiteInfoAction;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Requests\UpdateSiteInfoRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class PackageSiteInfoController extends Controller
{
    public function __construct(private readonly UpdateSiteInfoAction $updateSiteInfoAction) {}

    public function update(UpdateSiteInfoRequest $request, CloseoutPackage $package): RedirectResponse
    {
        $this->updateSiteInfoAction->execute($package, $request->toDTO());

        return redirect()->back()->with('success', 'Site information updated.');
    }
}
