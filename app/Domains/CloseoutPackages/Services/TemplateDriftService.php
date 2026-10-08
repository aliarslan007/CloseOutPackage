<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Services;

use App\Domains\CloseoutPackages\DTOs\DriftConflict;
use App\Domains\CloseoutPackages\DTOs\DriftReport;
use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\Templates\Models\ChecklistItem;
use App\Domains\Templates\Models\TemplatePictureCategory;
use App\Shared\Exceptions\TemplateDriftConflictException;
use Illuminate\Database\Eloquent\Builder;

/**
 * Detects and heals drift between a package's snapshot and its live template.
 *
 * The package snapshots template values at creation (see PackageInitializationService).
 * When an admin later edits the template, live drafts can no longer satisfy it.
 * Only DRAFT packages are affected — every other status is read-only.
 */
final class TemplateDriftService
{
    /**
     * Inspect the package for conflicts that block submission. Pass a stage to
     * scope the check to that stage (used when submitting one stage); omit it to
     * check the whole package (used when submitting the package).
     */
    public function detect(CloseoutPackage $package, ?PackageStage $stage = null): DriftReport
    {
        if ($package->status !== PackageStatus::DRAFT) {
            return DriftReport::clean();
        }

        $conflicts = $this->bucketConflicts($package, $stage);

        return new DriftReport(hasUnsafeDrift: $conflicts !== [], conflicts: $conflicts);
    }

    /**
     * Raise a 409 with the precise conflict payload when unsafe drift remains.
     */
    public function assertNoUnsafeDrift(CloseoutPackage $package, ?PackageStage $stage = null): void
    {
        $report = $this->detect($package, $stage);

        if ($report->hasUnsafeDrift) {
            throw new TemplateDriftConflictException($report->conflicts);
        }
    }

    /**
     * Persist the safe template changes. The template is the single source of
     * truth for a package's bucket cap, so this mirrors the live template both
     * up and down:
     *   - the snapshot can never drift from what the admin defined.
     * It also re-selects a category the template now makes required and drops
     * an explanation a template no longer asks for. Only writes rows that
     * actually change. Idempotent — safe to run on every page load.
     */
    public function applySafeChanges(CloseoutPackage $package): void
    {
        $template = $package->template()->with(['sections.categories', 'checklistItems'])->first();

        /** @var list<int> $stageIds */
        $stageIds = $package->stages()->pluck('id')->all();

        $selections = PackageCategorySelection::query()
            ->whereIn('package_stage_id', $stageIds)
            ->with('templatePictureCategory')
            ->get();

        foreach ($selections as $selection) {
            $category = $selection->templatePictureCategory;

            if (! $category instanceof TemplatePictureCategory || $category->trashed()) {
                if (
                    $selection->photos()->doesntExist()
                    && ! in_array($package->status, [PackageStatus::APPROVED, PackageStatus::CUSTOMER_REVIEW], true)
                ) {
                    $selection->delete();
                }

                continue;
            }

            $updates = [];

            if ($selection->picture_count !== $category->default_picture_count) {
                $updates['picture_count'] = $category->default_picture_count;
            }

            if ($category->is_required && ! $selection->is_selected) {
                $updates['is_selected'] = true;
            }

            if ($updates !== []) {
                $selection->update($updates);
            }
        }

        // Synchronize newly added categories from live template sections into package stages
        if ($template !== null) {
            foreach ($template->sections as $section) {
                $stage = $package->stages()->where('stage', $section->stage)->first();

                if ($stage === null) {
                    continue;
                }

                $existingCatIds = $stage->categorySelections()
                    ->pluck('template_picture_category_id')
                    ->all();

                foreach ($section->categories as $category) {
                    if (! $category->trashed() && ! in_array($category->id, $existingCatIds, true)) {
                        $stage->categorySelections()->create([
                            'template_picture_category_id' => $category->id,
                            'is_selected' => true,
                            'picture_count' => $category->default_picture_count,
                        ]);
                    }
                }
            }
        }

        // Clean up or heal existing checklist responses
        $responses = $package->checklistResponses()->with('checklistItem')->get();

        foreach ($responses as $response) {
            $item = $response->checklistItem;

            if (! $item instanceof ChecklistItem || $item->trashed() || ($template !== null && $item->template_id !== $template->id)) {
                $response->delete();

                continue;
            }

            if (! $item->has_explain_field && $response->explain !== null) {
                $response->update(['explain' => null]);
            }

            if (! $item->allows_na && $response->is_na) {
                $response->update([
                    'is_na' => false,
                    'answer' => null,
                ]);
            }
        }

        // Synchronize newly added checklist items from live template into package checklist responses
        if ($template !== null) {
            $existingItemIds = $package->checklistResponses()->pluck('checklist_item_id')->all();

            foreach ($template->checklistItems as $item) {
                if (! in_array($item->id, $existingItemIds, true)) {
                    $package->checklistResponses()->create([
                        'checklist_item_id' => $item->id,
                        'answer' => null,
                        'is_na' => false,
                        'explain' => null,
                    ]);
                }
            }
        }
    }

    /**
     * A conflict exists only when a template bucket shrank below the number of
     * photos actually uploaded — bucket removals the tech can still satisfy are safe.
     *
     * @return list<DriftConflict>
     */
    private function bucketConflicts(CloseoutPackage $package, ?PackageStage $stage): array
    {
        /** @var list<int> $stageIds */
        $stageIds = $stage !== null
            ? [$stage->id]
            : $package->stages()->pluck('id')->all();

        if ($stageIds === []) {
            return [];
        }

        $selections = PackageCategorySelection::query()
            ->whereIn('package_stage_id', $stageIds)
            ->with(['templatePictureCategory', 'stage'])
            ->withCount(['photos as active_photo_count' => function (Builder $query): void {
                $query->whereNull('replaced_at');
            }])
            ->get();

        $conflicts = [];

        foreach ($selections as $selection) {
            $category = $selection->templatePictureCategory;
            $stage = $selection->stage;

            if (! $category instanceof TemplatePictureCategory || $category->trashed() || ! $stage instanceof PackageStage) {
                continue;
            }

            $newBucket = $category->default_picture_count;
            $uploaded = $selection->active_photo_count;

            if ($newBucket >= $uploaded) {
                continue;
            }

            $conflicts[] = new DriftConflict(
                type: DriftConflict::TYPE_BUCKET_DECREASED,
                stageId: $stage->id,
                stageName: $stage->stage->label(),
                categoryId: $category->id,
                categoryName: $category->name,
                newValue: $newBucket,
                currentPhotoCount: $uploaded,
            );
        }

        return $conflicts;
    }
}
