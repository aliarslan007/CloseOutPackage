<?php

declare(strict_types=1);

namespace App\Domains\Approval\Controllers;

use App\Domains\Approval\Actions\ApprovePackageFinalAction;
use App\Domains\Approval\DTOs\FinalPackageApprovalDTO;
use App\Domains\Approval\Requests\FinalApprovalRequest;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class FinalApprovePackageController extends Controller
{
    public function __construct(private readonly ApprovePackageFinalAction $approveFinalAction) {}

    public function __invoke(FinalApprovalRequest $request, CloseoutPackage $package): RedirectResponse
    {
        $this->approveFinalAction->execute(
            new FinalPackageApprovalDTO(
                closeoutPackageId: $package->id,
                siteDetails: $request->validated('site_details') ?? [],
            ),
            $this->user(),
        );

        return redirect()->back()->with('success', 'Package approved.');
    }
}
