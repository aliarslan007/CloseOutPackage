<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Controllers;

use App\Domains\CloseoutPackages\Actions\SaveDraftAction;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Requests\SaveDraftRequest;
use App\Domains\Templates\Enums\StageType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class SaveStageDraftController extends Controller
{
    public function __construct(private readonly SaveDraftAction $saveDraftAction) {}

    public function __invoke(SaveDraftRequest $request, CloseoutPackage $package, StageType $stage): RedirectResponse
    {
        $stageRecord = $package->stages()->where('stage', $stage)->firstOrFail();

        $this->saveDraftAction->execute($package, $stageRecord, $request->toDTO());

        return redirect()->back()->with('success', 'Draft saved.');
    }
}
