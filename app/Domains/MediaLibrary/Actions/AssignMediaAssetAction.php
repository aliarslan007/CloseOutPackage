<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Actions;

use App\Domains\Approval\Enums\ApprovalDecision;
use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\EntityFeedback\Support\AssertFeedbackTargetNotWithdrawn;
use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Models\MediaAssetPhoto;
use App\Domains\MediaLibrary\Services\MediaAssetStorageService;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Photos\Events\PhotoUploaded;
use App\Domains\Photos\Models\Photo;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Exceptions\MediaAssetAlreadyAssignedException;
use App\Shared\Exceptions\MediaAssetNotReadyException;
use App\Shared\Exceptions\PhotoCountExceededException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Places a ready media-library asset into a COP category selection as a new
 * Photo. The file is copied to an independent Spatie Media record so the
 * library asset and the assigned Photo never share rows.
 */
final class AssignMediaAssetAction
{
    public function execute(int $mediaAssetId, int $categorySelectionId, User $assignedBy): Photo
    {
        $photos = $this->executeMany([$mediaAssetId], $categorySelectionId, $assignedBy);

        return $photos[0];
    }

    /**
     * @param  list<int>  $mediaAssetIds
     * @return list<Photo>
     */
    public function executeMany(array $mediaAssetIds, int $categorySelectionId, User $assignedBy): array
    {
        $assets = MediaAsset::query()->whereIn('id', $mediaAssetIds)->get();

        if ($assets->count() !== count($mediaAssetIds)) {
            throw new ModelNotFoundException('One or more media assets were not found.');
        }

        foreach ($assets as $asset) {
            Gate::authorize('assign', $asset);

            if ($asset->status !== MediaAssetStatus::READY) {
                throw new MediaAssetNotReadyException('This file has not finished processing yet. Please wait a moment and try again.');
            }
        }

        return DB::transaction(function () use ($assets, $categorySelectionId, $assignedBy): array {
            $selection = PackageCategorySelection::query()
                ->lockForUpdate()
                ->findOrFail($categorySelectionId);

            $stage = PackageStage::query()->findOrFail($selection->package_stage_id);
            $package = CloseoutPackage::query()->findOrFail($stage->closeout_package_id);

            $this->assertPackageAcceptsPhotos($package, $selection);

            $currentCount = Photo::query()
                ->where('package_category_selection_id', $selection->id)
                ->whereNull('replaced_at')
                ->count();

            if (($currentCount + $assets->count()) > $selection->picture_count) {
                $capacityMsg = $assets->count() === 1
                    ? "This category allows {$selection->picture_count} photo(s). All slots are filled."
                    : "This category allows {$selection->picture_count} photo(s). Selected photos exceed available capacity.";

                throw new PhotoCountExceededException($capacityMsg);
            }

            $createdPhotos = [];

            foreach ($assets as $asset) {
                $alreadyPlaced = $asset->photos()
                    ->where('package_category_selection_id', $selection->id)
                    ->exists();

                if ($alreadyPlaced) {
                    $assignedMsg = $assets->count() === 1
                        ? 'This file is already placed in this category.'
                        : "File [{$asset->original_filename}] is already placed in this category.";

                    throw new MediaAssetAlreadyAssignedException($assignedMsg);
                }

                $currentCount += 1;

                $isManager = $assignedBy->hasRole([Role::MANAGER->value, Role::ADMIN->value]);

                $photo = Photo::create([
                    'closeout_package_id' => $package->id,
                    'package_stage_id' => $selection->package_stage_id,
                    'package_category_selection_id' => $selection->id,
                    'uploaded_by' => $assignedBy->id,
                    'position' => $currentCount,
                    'status' => $isManager ? PhotoStatus::APPROVED : PhotoStatus::PENDING,
                    'is_approved' => $isManager ? true : null,
                    'approved_at' => $isManager ? now() : null,
                    'approved_by' => $isManager ? $assignedBy->id : null,
                    'gps_latitude' => $asset->gps_latitude,
                    'gps_longitude' => $asset->gps_longitude,
                    'gps_accuracy' => $asset->gps_accuracy,
                    'captured_at' => $asset->captured_at,
                ]);

                if ($isManager) {
                    $photo->photoApproval()->create([
                        'reviewed_by' => $assignedBy->id,
                        'decision' => ApprovalDecision::APPROVED,
                        'reason' => 'Automatically approved upon manager upload.',
                    ]);
                }

                app(MediaAssetStorageService::class)->copyToPhoto($asset, $photo);

                MediaAssetPhoto::create([
                    'media_asset_id' => $asset->id,
                    'photo_id' => $photo->id,
                ]);

                PhotoUploaded::dispatch($photo);
                $createdPhotos[] = $photo;
            }

            return $createdPhotos;
        });
    }

    private function assertPackageAcceptsPhotos(
        CloseoutPackage $package,
        PackageCategorySelection $selection
    ): void {
        // "From library" places a photo like any upload, so a category whose only
        // feedback the customer withdrew refuses it too.
        app(AssertFeedbackTargetNotWithdrawn::class)->forCategorySelection($package, $selection);

        if ($package->status === PackageStatus::DRAFT) {
            return;
        }

        $isFeedbackLoop = $package->isInCustomerReview()
            || $package->hasActiveFeedbackAssignedToTechnician()
            || $package->hasPendingFeedback()
            || in_array($package->status, [
                PackageStatus::FEEDBACK_RECEIVED,
                PackageStatus::REVISION_IN_PROGRESS,
                PackageStatus::CUSTOMER_REVIEW,
            ], true);

        if ($isFeedbackLoop) {
            return;
        }

        throw new InvalidStateTransitionException(
            'This package has been submitted. Only flagged photos can be replaced.',
        );
    }
}
