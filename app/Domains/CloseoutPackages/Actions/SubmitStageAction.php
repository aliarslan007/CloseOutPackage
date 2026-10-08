<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Actions;

use App\Domains\CloseoutPackages\DTOs\SubmitStageDTO;
use App\Domains\CloseoutPackages\Enums\StageStatus;
use App\Domains\CloseoutPackages\Events\StageSubmitted;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\CloseoutPackages\Services\RequiredCategoryValidator;
use App\Domains\CloseoutPackages\Services\StageAdvancementService;
use App\Domains\CloseoutPackages\Services\TemplateDriftService;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Users\Models\User;
use App\Shared\Exceptions\StageNotDraftSavedException;
use App\Shared\Exceptions\WrongStageException;
use Illuminate\Support\Facades\DB;

final class SubmitStageAction
{
    public function __construct(
        private readonly TemplateDriftService $driftService,
        private readonly RequiredCategoryValidator $requiredCategoryValidator,
    ) {}

    public function execute(SubmitStageDTO $dto, User $submitter): PackageStage
    {
        $stage = PackageStage::query()->findOrFail($dto->packageStageId);
        $package = CloseoutPackage::query()->findOrFail($stage->closeout_package_id);

        /** @var StageType $stageType */
        $stageType = $stage->stage;
        /** @var StageType $currentStage */
        $currentStage = $package->current_stage;
        /** @var StageStatus $stageStatus */
        $stageStatus = $stage->status;

        $stageOrder = [
            StageType::PRE_CONSTRUCTION->value => 0,
            StageType::CONSTRUCTION->value => 1,
            StageType::POST_COMPLETION->value => 2,
        ];
        $stageIndex = $stageOrder[$stageType->value] ?? 0;
        $currentIndex = $stageOrder[$currentStage->value] ?? 0;

        if ($stageIndex > $currentIndex) {
            throw new WrongStageException('This stage is not the current stage of the package.');
        }

        $this->driftService->applySafeChanges($package);

        $hasCategories = $stage->categorySelections()->where('is_selected', true)->exists()
            || ($package->template?->sections()->where('stage', $stageType)->first()?->categories()->exists() ?? false);

        if ($hasCategories && $stageStatus !== StageStatus::DRAFT_SAVED && $stageStatus !== StageStatus::SUBMITTED) {
            throw new StageNotDraftSavedException('Save a draft before submitting this stage.');
        }

        // The stage's uploaded photos must still fit the template's current buckets.
        $this->driftService->assertNoUnsafeDrift($package, $stage);

        // Every category the live template marks required must have a photo.
        $this->requiredCategoryValidator->assertSatisfied($package, $stage);

        return DB::transaction(function () use ($package, $stage, $submitter, $stageType, $stageOrder, $currentIndex): PackageStage {
            $stage->update([
                'status' => StageStatus::SUBMITTED,
                'submitted_at' => now(),
                'submitted_by' => $submitter->id,
            ]);

            $next = app(StageAdvancementService::class)->next($stageType);

            if ($next !== null) {
                $nextIndex = $stageOrder[$next->value] ?? 0;
                if ($nextIndex > $currentIndex) {
                    $package->update(['current_stage' => $next]);
                }
            }

            StageSubmitted::dispatch($package, $stage);

            return $stage;
        });
    }
}
