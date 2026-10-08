<?php

declare(strict_types=1);

namespace App\Domains\Photos\Actions;

use App\Domains\Approval\Enums\ApprovalDecision;
use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\EntityFeedback\Support\AssertFeedbackTargetNotWithdrawn;
use App\Domains\MediaLibrary\Actions\RegisterPhotoInLibraryAction;
use App\Domains\Photos\DTOs\UploadPhotoDTO;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Photos\Events\PhotoUploaded;
use App\Domains\Photos\Models\Photo;
use App\Domains\Photos\Services\GpsValidationService;
use App\Domains\Photos\Services\PhotoStorageService;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Exceptions\PhotoCountExceededException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class UploadPhotoAction
{
    public function execute(UploadPhotoDTO $dto, UploadedFile $file, User $uploader): Photo
    {
        $package = CloseoutPackage::query()->findOrFail($dto->closeoutPackageId);
        $stage = PackageStage::query()->findOrFail($dto->packageStageId);
        $package->loadMissing('project');

        app(ValidatePhotoOwnershipAction::class)->execute($package, $uploader, allowRevisionManager: true);

        $this->assertUploadAllowed($package, $dto);

        return DB::transaction(function () use ($package, $stage, $dto, $file, $uploader): Photo {
            $selection = PackageCategorySelection::query()
                ->lockForUpdate()
                ->findOrFail($dto->categorySelectionId);

            $isFeedbackLoop = $package->isInCustomerReview()
                || $package->hasActiveFeedbackAssignedToTechnician()
                || $package->hasPendingFeedback()
                || in_array($package->status, [
                    PackageStatus::FEEDBACK_RECEIVED,
                    PackageStatus::REVISION_IN_PROGRESS,
                    PackageStatus::CUSTOMER_REVIEW,
                ], true);

            if ($isFeedbackLoop) {
                if ($dto->position > $selection->picture_count) {
                    $selection->update(['picture_count' => $dto->position]);
                }
            } else {
                if ($dto->position > $selection->picture_count) {
                    throw new PhotoCountExceededException(
                        "Position {$dto->position} exceeds the allowed picture count of {$selection->picture_count}.",
                    );
                }
            }

            $existingPhoto = Photo::query()
                ->where('package_category_selection_id', $selection->id)
                ->where('position', $dto->position)
                ->whereNull('replaced_at')
                ->first();

            $currentPhotoCount = Photo::query()
                ->where('package_category_selection_id', $selection->id)
                ->whereNull('replaced_at')
                ->count();

            if ($existingPhoto === null && $currentPhotoCount >= $selection->picture_count) {
                if ($isFeedbackLoop) {
                    $selection->increment('picture_count');
                } else {
                    throw new PhotoCountExceededException(
                        'Bucket capacity is full. Remove an existing one to add a new one.',
                    );
                }
            }

            if ($existingPhoto !== null) {
                $existingPhoto->update([
                    'replaced_at' => now(),
                    'is_approved' => false,
                ]);
            }

            $gps = app(GpsValidationService::class)->normalize(
                $dto->gpsLatitude,
                $dto->gpsLongitude,
                $dto->gpsAccuracy,
            );

            if ($gps['latitude'] === null) {
                $gps = app(GpsValidationService::class)->fromFile($file);
            }

            $isManager = $uploader->hasAnyRole([Role::MANAGER->value, Role::ADMIN->value]);
            $shouldAutoApprove = $isManager;
            $approverId = $isManager
                ? $uploader->id
                : ($package->project?->manager_id ?? $uploader->id);

            $photo = Photo::create([
                'closeout_package_id' => $package->id,
                'package_stage_id' => $stage->id,
                'package_category_selection_id' => $selection->id,
                'uploaded_by' => $uploader->id,
                'position' => $dto->position,
                'notes' => $dto->notes,
                'status' => $shouldAutoApprove ? PhotoStatus::APPROVED : PhotoStatus::PENDING,
                'is_approved' => $shouldAutoApprove ? true : null,
                'approved_at' => $shouldAutoApprove ? now() : null,
                'approved_by' => $shouldAutoApprove ? $approverId : null,
                'gps_latitude' => $gps['latitude'],
                'gps_longitude' => $gps['longitude'],
                'gps_accuracy' => $gps['accuracy'],
                'captured_at' => $dto->capturedAt,
            ]);

            if ($shouldAutoApprove) {
                $photo->photoApproval()->create([
                    'reviewed_by' => $approverId,
                    'decision' => ApprovalDecision::APPROVED,
                    'reason' => 'Automatically approved upon manager upload.',
                ]);
            }

            $media = app(PhotoStorageService::class)->store($photo, $file);

            // Mirror into the media library (deduped, project-linked).
            // Non-fatal by design — never blocks the COP photo itself.
            app(RegisterPhotoInLibraryAction::class)->execute($photo, $media, $uploader, $package->project);

            PhotoUploaded::dispatch($photo);

            return $photo;
        });
    }

    private function assertUploadAllowed(CloseoutPackage $package, UploadPhotoDTO $dto): void
    {
        // A category whose only feedback the customer withdrew is off the table,
        // so neither the upload button nor the camera may add to it.
        app(AssertFeedbackTargetNotWithdrawn::class)->forCategorySelection(
            $package,
            $dto->categorySelectionId
        );

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

        $isRejectedReplacement = $package->status === PackageStatus::REJECTED
            && $this->hasReplaceablePhotoAt($dto->categorySelectionId, $dto->position);

        if ($isRejectedReplacement) {
            return;
        }

        throw new InvalidStateTransitionException(
            'This package has been submitted. Only flagged photos can be replaced.',
        );
    }

    private function hasReplaceablePhotoAt(int $categorySelectionId, int $position): bool
    {
        return Photo::query()
            ->where('package_category_selection_id', $categorySelectionId)
            ->where('position', $position)
            ->whereNull('replaced_at')
            ->whereIn('status', [
                PhotoStatus::FLAGGED->value,
                PhotoStatus::REJECTED->value,
                PhotoStatus::REPLACEMENT_REQUESTED->value,
            ])
            ->exists();
    }
}
