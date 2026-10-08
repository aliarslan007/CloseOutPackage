<?php

declare(strict_types=1);

namespace App\Domains\Photos\Actions;

use App\Domains\Approval\Enums\ApprovalDecision;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Domains\MediaLibrary\Actions\RegisterPhotoInLibraryAction;
use App\Domains\Photos\DTOs\ReplacePhotoDTO;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Photos\Events\PhotoReplaced;
use App\Domains\Photos\Models\Photo;
use App\Domains\Photos\Services\GpsValidationService;
use App\Domains\Photos\Services\PhotoStorageService;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class ReplacePhotoAction
{
    public function execute(UploadedFile $file, ReplacePhotoDTO $dto, User $uploader): Photo
    {
        $oldPhoto = Photo::query()->findOrFail($dto->photoId);
        $package = CloseoutPackage::query()->findOrFail($oldPhoto->closeout_package_id);
        $package->loadMissing('project');

        app(AssertPackagePhotosEditableAction::class)->execute($package, $oldPhoto);
        app(ValidatePhotoOwnershipAction::class)->execute($package, $uploader, allowRevisionManager: true);

        return DB::transaction(function () use ($oldPhoto, $file, $dto, $uploader, $package): Photo {
            $gps = app(GpsValidationService::class)->normalize(
                $dto->gpsLatitude,
                $dto->gpsLongitude,
                $dto->gpsAccuracy,
            );

            $isManager = $uploader->hasAnyRole([Role::MANAGER->value, Role::ADMIN->value]);
            $shouldAutoApprove = $isManager;

            $approverId = $isManager
                ? $uploader->id
                : ($package->project?->manager_id ?? $uploader->id);

            $newPhoto = Photo::create([
                'closeout_package_id' => $oldPhoto->closeout_package_id,
                'package_stage_id' => $oldPhoto->package_stage_id,
                'package_category_selection_id' => $oldPhoto->package_category_selection_id,
                'uploaded_by' => $uploader->id,
                'position' => $oldPhoto->position,
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
                $newPhoto->photoApproval()->create([
                    'reviewed_by' => $approverId,
                    'decision' => ApprovalDecision::APPROVED,
                    'reason' => 'Automatically approved upon manager replacement.',
                ]);
            }

            $media = app(PhotoStorageService::class)->store($newPhoto, $file);

            // Mirror into the media library (deduped, project-linked).
            // Non-fatal by design — never blocks the replacement itself.
            app(RegisterPhotoInLibraryAction::class)->execute($newPhoto, $media, $uploader, $package->project);

            $oldPhoto->update([
                'replaced_at' => now(),
                'replaced_by_photo_id' => $newPhoto->id,
                'is_approved' => false,
            ]);

            // The customer's complaint follows the replacement rather than being
            // silently closed: the new photo becomes the feedback target, so the
            // photo node stays visible to the manager and technician and the
            // round is only resolved when the manager confirms the fix.
            EntityFeedbackNote::query()
                ->where('entity_type', FeedbackEntityType::CLOSEOUT_PACKAGE->value)
                ->where('entity_id', $oldPhoto->closeout_package_id)
                ->where(function ($query) use ($oldPhoto) {
                    $query->where('source_photo_type', $oldPhoto->getMorphClass())
                        ->orWhere('source_photo_type', 'photo');
                })
                ->where('source_photo_id', $oldPhoto->id)
                ->whereNull('resolved_at')
                ->update(['source_photo_id' => $newPhoto->id]);

            PhotoReplaced::dispatch($oldPhoto, $newPhoto);

            return $newPhoto;
        });
    }
}
