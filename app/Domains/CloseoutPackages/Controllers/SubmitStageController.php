<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Controllers;

use App\Domains\CloseoutPackages\Actions\SubmitStageAction;
use App\Domains\CloseoutPackages\DTOs\SubmitStageDTO;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Templates\Enums\StageType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class SubmitStageController extends Controller
{
    public function __construct(private readonly SubmitStageAction $submitStageAction) {}

    public function __invoke(CloseoutPackage $package, StageType $stage): RedirectResponse
    {
        $this->authorize('submitStage', $package);

        $stageRecord = $package->stages()->where('stage', $stage)->firstOrFail();

        $this->submitStageAction->execute(
            new SubmitStageDTO(packageStageId: $stageRecord->id),
            $this->user(),
        );

        return redirect()->back()->with('success', 'Stage submitted.');
    }
}
