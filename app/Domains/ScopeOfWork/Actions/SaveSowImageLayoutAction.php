<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Actions;

use App\Domains\ScopeOfWork\Models\SowReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Persists the entire photo board in one atomic write: group order, each
 * image's group membership, and each image's global sort order.
 *
 * Keeping `sow_annotated_images.sort_order` globally unique and contiguous is
 * what stops a per-group reorder from producing colliding values (which would
 * make `annotatedImages()` — ordered by sort_order alone — return rows in an
 * arbitrary order). Callers send their intended order; this action re-indexes
 * from 0 so gaps and duplicates are impossible by construction.
 */
final class SaveSowImageLayoutAction
{
    public function __construct(
        private readonly AssertSowImagesEditableAction $assertEditable,
    ) {}

    /**
     * @param  list<array{id: int, sort_order: int}>  $groups
     * @param  list<array{id: int, sow_image_group_id: int|null, sort_order: int}>  $images
     */
    public function execute(SowReport $report, array $groups, array $images): void
    {
        $this->assertEditable->execute($report, 'Only draft or in-review SOWs can be regrouped.');

        $orderedGroups = $this->sortedByIntent($groups);

        $this->guardOwnership($report, $orderedGroups, $images);

        DB::transaction(function () use ($report, $orderedGroups, $images): void {
            // Groups settle first — their resulting order defines the sequence in
            // which the image groups are laid out below.
            foreach ($orderedGroups as $position => $group) {
                $report->imageGroups()
                    ->whereKey($group['id'])
                    ->update(['sort_order' => $position]);
            }

            foreach ($this->flatten($orderedGroups, $images) as $position => $image) {
                $report->annotatedImages()
                    ->whereKey($image['id'])
                    ->update([
                        'sow_image_group_id' => $image['sow_image_group_id'],
                        'sort_order' => $position,
                    ]);
            }
        });
    }

    /**
     * Reject any id that does not belong to this report — an unscoped update
     * would be a cross-report write (the routes carry no ownership constraint).
     *
     * @param  list<array{id: int, sort_order: int}>  $groups
     * @param  list<array{id: int, sow_image_group_id: int|null, sort_order: int}>  $images
     */
    private function guardOwnership(SowReport $report, array $groups, array $images): void
    {
        $imageIds = array_map(static fn (array $image): int => $image['id'], $images);
        $groupIds = array_map(static fn (array $group): int => $group['id'], $groups);

        if (count($imageIds) !== count(array_unique($imageIds))) {
            throw ValidationException::withMessages(['images' => 'Duplicate image ids in layout payload.']);
        }

        if (count($groupIds) !== count(array_unique($groupIds))) {
            throw ValidationException::withMessages(['groups' => 'Duplicate group ids in layout payload.']);
        }

        // Duplicates are already excluded, so a row-count match proves every
        // referenced id belongs to this report. Mirrors ReorderAnnotatedImagesAction.
        $ownedImages = $report->annotatedImages()->whereIn('id', $imageIds)->count();

        if ($ownedImages !== count($imageIds)) {
            throw ValidationException::withMessages(['images' => 'Some photos do not belong to this SOW.']);
        }

        $ownedGroups = $report->imageGroups()->whereIn('id', $groupIds)->count();

        if ($ownedGroups !== count($groupIds)) {
            throw ValidationException::withMessages(['groups' => 'Some groups do not belong to this SOW.']);
        }

        // An image may only point at a group that exists on this same report.
        $referenced = array_values(array_unique(array_filter(
            array_map(static fn (array $image): ?int => $image['sow_image_group_id'], $images),
            static fn (?int $id): bool => $id !== null,
        )));

        if ($referenced !== [] && $report->imageGroups()->whereIn('id', $referenced)->count() !== count($referenced)) {
            throw ValidationException::withMessages(['images' => 'Some photos reference an unknown group.']);
        }
    }

    /**
     * Flattens the board into one global sequence: each group's photos in the
     * group's order, then ungrouped photos last.
     *
     * An image's incoming `sort_order` means "position within its group", so
     * two groups can both start at 0 without their rows colliding in the flat
     * `annotatedImages()` list — this method is what makes that safe.
     *
     * @param  list<array{id: int, sort_order: int}>  $orderedGroups
     * @param  list<array{id: int, sow_image_group_id: int|null, sort_order: int}>  $images
     * @return list<array{id: int, sow_image_group_id: int|null, sort_order: int}>
     */
    private function flatten(array $orderedGroups, array $images): array
    {
        $rank = [];
        foreach ($orderedGroups as $position => $group) {
            $rank[$group['id']] = $position;
        }

        // Ungrouped photos sort after every group.
        $ungroupedRank = count($orderedGroups);

        usort($images, static function (array $a, array $b) use ($rank, $ungroupedRank): int {
            $rankOf = static fn (?int $groupId): int => $groupId === null
                ? $ungroupedRank
                : $rank[$groupId] ?? $ungroupedRank;

            return $rankOf($a['sow_image_group_id']) <=> $rankOf($b['sow_image_group_id'])
                ?: $a['sort_order'] <=> $b['sort_order'];
        });

        return $images;
    }

    /**
     * Order entries by the caller's intent. Callers then persist the array
     * index, so the stored sequence is always contiguous and duplicate-free.
     *
     * @param  list<array{id: int, sort_order: int}>  $groups
     * @return list<array{id: int, sort_order: int}>
     */
    private function sortedByIntent(array $groups): array
    {
        usort($groups, static fn (array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);

        return $groups;
    }
}
