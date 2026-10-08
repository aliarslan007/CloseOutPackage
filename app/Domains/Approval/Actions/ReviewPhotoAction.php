<?php

declare(strict_types=1);

namespace App\Domains\Approval\Actions;

use App\Domains\Approval\DTOs\ReviewPhotoDTO;
use App\Domains\Approval\Enums\ApprovalDecision;
use App\Domains\Approval\Events\PhotoReviewed;
use App\Domains\Approval\Events\ReplacementRequested;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Photos\Actions\AssertPackagePhotosEditableAction;
use App\Domains\Photos\Actions\AssertPackageReviewableAction;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Photos\Models\Photo;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;

final class ReviewPhotoAction
{
    public function execute(ReviewPhotoDTO $dto, User $reviewer): Photo
    {
        $photo = Photo::query()->findOrFail($dto->photoId);
        $package = CloseoutPackage::query()->find($photo->closeout_package_id);

        app(AssertPackagePhotosEditableAction::class)->execute($package, $photo);
        app(AssertPackageReviewableAction::class)->execute($package);

        $wasApproved = (bool) $photo->is_approved;

        return DB::transaction(function () use ($photo, $dto, $reviewer, $wasApproved, $package): Photo {
            $photo->photoApproval()->updateOrCreate(
                [],
                [
                    'reviewed_by' => $reviewer->id,
                    'decision' => $dto->decision,
                    'reason' => $dto->reason,
                ],
            );

            $photo->update($this->photoAttributes($dto->decision, $dto->reason, $reviewer));

            if ($wasApproved && $this->approvedValue($dto->decision) !== true) {
                $this->invalidateFinalPdf($package, $photo);
            }

            PhotoReviewed::dispatch($photo, $dto->decision, $reviewer);

            if ($dto->decision === ApprovalDecision::REPLACEMENT_REQUESTED) {
                ReplacementRequested::dispatch($photo, $reviewer);
            }

            return $photo;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function photoAttributes(ApprovalDecision $decision, ?string $reason, User $reviewer): array
    {
        $attributes = [
            'status' => $this->statusFor($decision),
            'is_approved' => $this->approvedValue($decision),
        ];

        if ($decision === ApprovalDecision::FLAGGED) {
            $attributes['flagged_at'] = now();
            $attributes['flagged_by'] = $reviewer->id;
            $attributes['flag_reason'] = $reason;
        } else {
            $attributes['flag_reason'] = null;
        }

        if ($decision === ApprovalDecision::APPROVED) {
            $attributes['approved_at'] = now();
            $attributes['approved_by'] = $reviewer->id;
        }

        return $attributes;
    }

    private function invalidateFinalPdf(?CloseoutPackage $package, Photo $photo): void
    {
        if ($package === null || $package->final_pdf_path === null) {
            return;
        }

        $package->update([
            'final_pdf_path' => null,
            'final_pdf_generated_at' => null,
        ]);

        activity('media')
            ->performedOn($package)
            ->event('pdf.invalidated')
            ->withProperties(['photo_id' => $photo->id])
            ->log('Final PDF invalidated after photo re-review');
    }

    private function statusFor(ApprovalDecision $decision): PhotoStatus
    {
        return match ($decision) {
            ApprovalDecision::APPROVED => PhotoStatus::APPROVED,
            ApprovalDecision::FLAGGED => PhotoStatus::FLAGGED,
            ApprovalDecision::REJECTED => PhotoStatus::REJECTED,
            ApprovalDecision::REPLACEMENT_REQUESTED => PhotoStatus::REPLACEMENT_REQUESTED,
        };
    }

    private function approvedValue(ApprovalDecision $decision): ?bool
    {
        return match ($decision) {
            ApprovalDecision::APPROVED => true,
            ApprovalDecision::REJECTED, ApprovalDecision::REPLACEMENT_REQUESTED => false,
            ApprovalDecision::FLAGGED => null,
        };
    }
}
