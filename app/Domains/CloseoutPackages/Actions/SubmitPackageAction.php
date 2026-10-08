<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Actions;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Enums\StageStatus;
use App\Domains\CloseoutPackages\Events\PackageSubmitted;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Services\RequiredCategoryValidator;
use App\Domains\CloseoutPackages\Services\TemplateDriftService;
use App\Domains\Users\Models\User;
use App\Shared\Exceptions\ChecklistIncompleteException;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\DB;

final class SubmitPackageAction
{
    public function __construct(
        private readonly TemplateDriftService $driftService,
        private readonly RequiredCategoryValidator $requiredCategoryValidator,
    ) {}

    public function execute(CloseoutPackage $package, User $submitter): CloseoutPackage
    {
        $this->driftService->applySafeChanges($package);
        $this->assertSubmittable($package);
        $this->driftService->assertNoUnsafeDrift($package);
        $this->requiredCategoryValidator->assertSatisfied($package);
        $this->assertChecklistComplete($package);

        return DB::transaction(function () use ($package, $submitter): CloseoutPackage {
            $package->update([
                'status' => PackageStatus::SUBMITTED,
                'submitted_at' => now(),
            ]);

            PackageSubmitted::dispatch($package, $submitter);

            return $package;
        });
    }

    private function assertSubmittable(CloseoutPackage $package): void
    {
        if ($package->status !== PackageStatus::DRAFT) {
            throw new InvalidStateTransitionException('Only draft packages can be submitted.');
        }

        $hasUnsubmittedStage = $package->stages()
            ->where('status', '!=', StageStatus::SUBMITTED->value)
            ->exists();

        if ($hasUnsubmittedStage) {
            throw new InvalidStateTransitionException('Submit all stages before submitting the package.');
        }
    }

    private function assertChecklistComplete(CloseoutPackage $package): void
    {
        $items = $package->template()->with('checklistItems')->firstOrFail()->checklistItems;
        $responses = $package->checklistResponses()->get()->keyBy('checklist_item_id');

        foreach ($items as $item) {
            $response = $responses->get($item->id);

            if ($response === null || ($response->answer === null && ! $response->is_na)) {
                throw new ChecklistIncompleteException('Please answer all checklist items before submitting the package.');
            }

            if ($response->is_na) {
                if (! $item->allows_na) {
                    throw new ChecklistIncompleteException("\"{$item->label}\" does not allow N/A as an answer.");
                }

                continue;
            }

            // Answer notes are optional for every answer — they never block submission.
        }
    }
}
