<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Support;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Domains\Photos\Models\Photo;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Database\Eloquent\Builder;

/**
 * Refuses work on the one picture or section the customer dropped.
 *
 * The package-wide unlock (hasPendingFeedback) stays open while any issue is
 * live, so staff keep working the rest of a review. This is the per-target half:
 * once every issue on a photo, its category, or the section above it has been
 * withdrawn, that item is frozen - delete, replace, upload, from-library and
 * note edits all refuse, even from a tab that has not seen the withdrawal yet.
 *
 * Targets are matched as (type, id) pairs, never on a bare id: template sections,
 * template categories and per-package category selections share id spaces that
 * overlap on a plain number, and a collision would freeze the wrong item.
 */
final class AssertFeedbackTargetNotWithdrawn
{
    public function forPhoto(?CloseoutPackage $package, Photo $photo): void
    {
        $this->assertNotWithdrawn($package, [
            ['photo', (int) $photo->id],
            ...$this->selectionTargets($photo->package_category_selection_id),
        ]);
    }

    /** Takes the loaded selection or just its id, whichever the caller has. */
    public function forCategorySelection(?CloseoutPackage $package, PackageCategorySelection|int|null $selection): void
    {
        $this->assertNotWithdrawn($package, $this->selectionTargets($selection));
    }

    /**
     * The id spaces one category lives in: its own selection id, the template
     * category it came from, and the section above that. A note filed as a photo
     * comment belongs to the first list, not this one.
     *
     * @return list<array{string, int}>
     */
    private function selectionTargets(PackageCategorySelection|int|null $selection): array
    {
        $selection = $selection instanceof PackageCategorySelection
            ? $selection
            : ($selection === null ? null : PackageCategorySelection::query()->find($selection));

        if ($selection === null) {
            return [];
        }

        $targets = [['category', (int) $selection->id]];
        $category = $selection->templatePictureCategory;

        if ($category !== null) {
            $targets[] = ['category', (int) $category->id];

            if ($category->template_section_id !== null) {
                $targets[] = ['section', (int) $category->template_section_id];
            }
        }

        return $targets;
    }

    /**
     * Blocked only when the item actually carries feedback and every message of
     * it was withdrawn - no feedback at all means nothing was cancelled, and one
     * live issue means the customer is still asking for work here.
     *
     * @param  list<array{string, int}>  $targets
     */
    private function assertNotWithdrawn(?CloseoutPackage $package, array $targets): void
    {
        $round = $package?->latestActiveFeedbackRound();

        if ($round === null || $targets === []) {
            return;
        }

        $notes = $round->notes()
            ->where(fn (Builder $query) => $this->addTargetClauses($query, $targets))
            ->get(['id', 'withdrawn_at']);

        if ($notes->isNotEmpty() && $notes->every(fn ($note) => $note->isWithdrawn())) {
            throw new InvalidStateTransitionException(
                'The customer withdrew the feedback on this item, so it can no longer be changed.'
            );
        }
    }

    /**
     * @param  Builder<EntityFeedbackNote>  $query
     * @param  list<array{string, int}>  $targets
     */
    private function addTargetClauses(Builder $query, array $targets): void
    {
        foreach ($targets as [$type, $id]) {
            $query->orWhere(function (Builder $clause) use ($type, $id): void {
                if ($type === 'photo') {
                    $clause->where('source_photo_id', $id);
                    $clause->where(fn (Builder $t) => $t->whereNull('source_photo_type')->orWhere('source_photo_type', 'photo'));

                    return;
                }

                // A section/category issue may be filed either against the
                // specific target id, or as a general issue on that whole
                // container. A withdrawn general section/category issue freezes
                // the container, not just one addressed photo.
                $clause->where(function (Builder $target) use ($type, $id): void {
                    $target->where('source_photo_type', $type)
                        ->where(function (Builder $targetId) use ($id): void {
                            $targetId->where('source_photo_id', $id)->orWhereNull('source_photo_id');
                        });
                });
            });
        }
    }
}
