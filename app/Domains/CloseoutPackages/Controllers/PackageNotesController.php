<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Controllers;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Requests\UpdatePackageNotesRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class PackageNotesController extends Controller
{
    public function update(UpdatePackageNotesRequest $request, CloseoutPackage $package): RedirectResponse
    {
        $package->update(['notes' => $request->notes()]);

        return redirect()->back()->with('success', 'Site notes saved.');
    }
}
