<?php

declare(strict_types=1);

namespace App\Domains\Approval\Services;

use App\Domains\Approval\DTOs\ReadinessFailure;
use App\Domains\Approval\DTOs\ReadinessReport;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Photos\Models\Photo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class ApprovalReadinessService
{
    private const REQUIRED_SITE_INFO_FIELDS = [
        'site_name',
        'address',
        'complete_date',
        'author_name',
        'author_email',
        'pm_name',
        'pm_email',
    ];

    public function check(CloseoutPackage $package): ReadinessReport
    {
        /** @var list<int> $stageIds */
        $stageIds = $package->relationLoaded('stages')
            ? $package->stages->pluck('id')->all()
            : $package->stages()->pluck('id')->all();

        $failures = [
            ...$this->siteInfoFailures($package),
            ...$this->checklistFailures($package),
            ...$this->photosUploadedFailures($stageIds),
            ...$this->pendingReplacementFailures($stageIds),
            ...$this->unreviewedPhotoFailures($stageIds),
        ];

        return new ReadinessReport(isReady: $failures === [], failures: $failures);
    }

    /**
     * @return list<ReadinessFailure>
     */
    private function siteInfoFailures(CloseoutPackage $package): array
    {
        $package->loadMissing(['project.manager', 'creator', 'siteInfo.authorUser', 'siteInfo.pmUser']);
        $siteInfo = $package->siteInfo;
        $project = $package->project;
        $manager = $project?->manager;
        $creator = $package->creator;

        $missing = [];
        foreach (self::REQUIRED_SITE_INFO_FIELDS as $field) {
            $value = $siteInfo?->getAttribute($field);

            if ($this->isEmptyValue($value)) {
                $value = match ($field) {
                    'site_name' => $project?->name,
                    'address' => $project?->address,
                    'complete_date' => $project?->completed_date,
                    'author_name' => $creator?->name,
                    'author_email' => $creator?->email,
                    'pm_name' => $manager?->name,
                    'pm_email' => $manager?->email,
                    default => null,
                };
            }

            if ($this->isEmptyValue($value)) {
                $missing[] = $field;
            }
        }

        if ($missing === []) {
            return [];
        }

        return [
            new ReadinessFailure(
                rule: 'site_info_complete',
                message: 'Site information is incomplete. Missing: '.implode(', ', $missing),
                context: ['missing_fields' => $missing],
            ),
        ];
    }

    /**
     * @return list<ReadinessFailure>
     */
    private function checklistFailures(CloseoutPackage $package): array
    {
        $package->loadMissing('checklistResponses.checklistItem');

        $unanswered = $package->checklistResponses
            ->filter(function ($response): bool {
                if ($response->is_na) {
                    return ! ($response->checklistItem?->allows_na ?? false);
                }

                return $response->answer === null;
            });

        if ($unanswered->isEmpty()) {
            return [];
        }

        return [
            new ReadinessFailure(
                rule: 'checklist_answered',
                message: 'Checklist has '.$unanswered->count().' unanswered item(s).',
                context: [
                    'unanswered_items' => $unanswered->map(
                        fn ($response): array => ['id' => $response->checklist_item_id, 'label' => $response->checklistItem?->label],
                    )->values()->all(),
                ],
            ),
        ];
    }

    /**
     * @param  list<int>  $stageIds
     * @return list<ReadinessFailure>
     */
    private function photosUploadedFailures(array $stageIds): array
    {
        if ($stageIds === []) {
            return [
                new ReadinessFailure(
                    rule: 'photos_uploaded',
                    message: 'Package stages have not been initialized.',
                    context: ['stages_count' => 0],
                ),
            ];
        }

        $selections = PackageCategorySelection::query()
            ->whereIn('package_stage_id', $stageIds)
            ->where('is_selected', true)
            ->with('templatePictureCategory')
            ->withCount(['photos as uploaded_photos' => function (Builder $query): void {
                $query->whereNotIn('status', [PhotoStatus::REJECTED->value]);
            }])
            ->get();

        $failures = [];

        foreach ($selections as $selection) {
            $category = $selection->templatePictureCategory;

            if ($category === null || $category->trashed() || ! $category->is_required) {
                continue;
            }

            $uploaded = $selection->uploaded_photos;
            $required = 1;

            if ($uploaded < $required) {
                $failures[] = new ReadinessFailure(
                    rule: 'photos_uploaded',
                    message: "Category '{$category->name}' requires at least {$required} photo(s), only {$uploaded} uploaded.",
                    context: [
                        'category_id' => $selection->id,
                        'required' => $required,
                        'uploaded' => $uploaded,
                        'capacity' => $selection->picture_count,
                    ],
                );
            }
        }

        return $failures;
    }

    /**
     * @param  list<int>  $stageIds
     * @return list<ReadinessFailure>
     */
    private function pendingReplacementFailures(array $stageIds): array
    {
        if ($stageIds === []) {
            return [];
        }

        $photos = $this->photosInStages($stageIds, [
            PhotoStatus::FLAGGED,
            PhotoStatus::REPLACEMENT_REQUESTED,
        ]);

        return $photos->map(function (Photo $photo): ReadinessFailure {
            $needsReplacement = $photo->status === PhotoStatus::FLAGGED
                ? 'is flagged and needs a replacement'
                : 'has a pending replacement request';

            return new ReadinessFailure(
                rule: 'no_pending_replacements',
                message: "Photo in category '{$this->categoryName($photo)}' {$needsReplacement}.",
                context: ['photo_id' => $photo->id, 'category_name' => $this->categoryName($photo)],
            );
        })->values()->all();
    }

    /**
     * @param  list<int>  $stageIds
     * @return list<ReadinessFailure>
     */
    private function unreviewedPhotoFailures(array $stageIds): array
    {
        if ($stageIds === []) {
            return [];
        }

        $photos = $this->photosInStages($stageIds, [PhotoStatus::PENDING]);

        return $photos->map(
            fn (Photo $photo): ReadinessFailure => new ReadinessFailure(
                rule: 'all_photos_reviewed',
                message: "Photo in category '{$this->categoryName($photo)}' has not been reviewed yet.",
                context: ['photo_id' => $photo->id, 'category_name' => $this->categoryName($photo)],
            ),
        )->values()->all();
    }

    /**
     * @param  list<int>  $stageIds
     * @param  list<PhotoStatus>  $statuses
     * @return Collection<int, Photo>
     */
    private function photosInStages(array $stageIds, array $statuses): Collection
    {
        $statusValues = array_map(fn (PhotoStatus $status): string => $status->value, $statuses);

        return Photo::query()
            ->whereIn('package_stage_id', $stageIds)
            ->whereIn('status', $statusValues)
            ->whereNull('replaced_at')
            ->with('categorySelection.templatePictureCategory')
            ->get();
    }

    private function categoryName(Photo $photo): string
    {
        $selection = $photo->categorySelection;
        $category = $selection?->templatePictureCategory;

        return $category !== null ? $category->name : 'Unknown';
    }

    private function isEmptyValue(mixed $value): bool
    {
        return $value === null || $value === '';
    }
}
