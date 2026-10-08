<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Actions;

use App\Domains\CloseoutPackages\DTOs\SaveDraftDTO;
use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Enums\StageStatus;
use App\Domains\CloseoutPackages\Events\PackageDraftSaved;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\CloseoutPackages\Services\TemplateDriftService;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\DB;

final class SaveDraftAction
{
    public function __construct(private readonly TemplateDriftService $driftService) {}

    public function execute(CloseoutPackage $package, PackageStage $stage, SaveDraftDTO $dto): PackageStage
    {
        $isFeedbackLoop = $package->isInCustomerReview()
            || $package->hasActiveFeedbackAssignedToTechnician()
            || $package->hasPendingFeedback()
            || in_array($package->status, [
                PackageStatus::FEEDBACK_RECEIVED,
                PackageStatus::REVISION_IN_PROGRESS,
                PackageStatus::CUSTOMER_REVIEW,
            ], true);

        if (! $isFeedbackLoop && $package->status !== PackageStatus::DRAFT) {
            throw new InvalidStateTransitionException('This package has been submitted and can no longer be edited.');
        }

        return DB::transaction(function () use ($package, $stage, $dto): PackageStage {
            foreach ($dto->categorySelections as $selection) {
                $stage->categorySelections()->updateOrCreate(
                    ['template_picture_category_id' => $selection['category_id']],
                    [
                        'is_selected' => $selection['is_selected'],
                        'picture_count' => $selection['picture_count'],
                        'user_heading' => $selection['user_heading'] ?? null,
                        'user_notes' => $selection['user_notes'] ?? null,
                    ],
                );
            }

            foreach ($dto->checklistResponses ?? [] as $response) {
                $package->checklistResponses()->updateOrCreate(
                    ['checklist_item_id' => $response['checklist_item_id']],
                    [
                        'answer' => $response['answer'] ?? null,
                        'explain' => $response['explain'] ?? null,
                        'is_na' => (bool) ($response['is_na'] ?? false),
                    ],
                );
            }

            // The live template owns bucket caps, required categories and explain
            // fields, so heal after the client's writes to let the template win.
            $this->driftService->applySafeChanges($package);

            if ($stage->status !== StageStatus::SUBMITTED) {
                $stage->update([
                    'status' => StageStatus::DRAFT_SAVED,
                    'draft_saved_at' => now(),
                ]);
            }

            PackageDraftSaved::dispatch($package, $stage);

            return $stage;
        });
    }
}
