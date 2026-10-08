<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Actions;

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderPhoto;
use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ApplyChangeOrderFeedbackPhotoAction
{
    public function execute(
        EntityFeedbackRound $round,
        UploadedFile $file,
        bool $replaceInChangeOrder,
        bool $addToChangeOrderPhotos,
        ?int $sourcePhotoId = null,
        ?int $replyToNoteId = null,
        ?int $uploadedBy = null,
    ): void {
        if ($round->entity_type !== FeedbackEntityType::CHANGE_ORDER) {
            return;
        }

        if (! $replaceInChangeOrder && ! $addToChangeOrderPhotos) {
            return;
        }

        /** @var ChangeOrder|null $changeOrder */
        $changeOrder = ChangeOrder::query()->find($round->entity_id);
        if ($changeOrder === null) {
            return;
        }

        $disk = Storage::disk('private');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $storagePath = 'change-orders/'.$changeOrder->getKey().'/'.Str::uuid().'.'.$extension;
        $content = $file->getContent();
        $originalName = $file->getClientOriginalName();
        $mime = (string) $file->getClientMimeType();
        $size = (int) $file->getSize();

        $targetPhotoId = $sourcePhotoId;
        if ($targetPhotoId === null && $replyToNoteId !== null) {
            $targetPhotoId = $round->notes()->whereKey($replyToNoteId)->value('source_photo_id');
        }

        if ($replaceInChangeOrder) {
            /** @var ChangeOrderPhoto|null $targetPhoto */
            $targetPhoto = null;
            if ($targetPhotoId !== null) {
                // The review UI addresses a change-order photo by its media
                // asset id, falling back to the photo's own primary key for
                // photos uploaded straight onto the change order. Resolving
                // only the primary key missed the media-library case, and the
                // lookup below then overwrote the first photo in the change
                // order instead of the one the customer had flagged.
                //
                // The OR is grouped so it cannot escape this change order.
                $targetPhoto = $changeOrder->photos()
                    ->where(function (Builder $query) use ($targetPhotoId): void {
                        $query
                            ->where('media_asset_id', $targetPhotoId)
                            ->orWhere('id', $targetPhotoId);
                    })
                    ->first();
            }

            // With no id to go on, only a single-photo change order is
            // unambiguous. Overwriting the first of several would destroy a
            // photo nobody asked to change.
            if ($targetPhoto === null && $changeOrder->photos()->count() === 1) {
                $targetPhoto = $changeOrder->photos()->first();
            }

            if ($targetPhoto !== null) {
                $oldPath = $targetPhoto->path;
                if ($disk->put($storagePath, $content)) {
                    $targetPhoto->update([
                        'original_name' => $originalName,
                        'path' => $storagePath,
                        'mime_type' => $mime,
                        'size_bytes' => $size,
                    ]);
                    if ($oldPath !== null && $oldPath !== $storagePath) {
                        $disk->delete($oldPath);
                    }
                }
            } elseif ($changeOrder->photos()->count() === 0 && $changeOrder->photos()->count() < ChangeOrder::MAX_PHOTOS) {
                if ($disk->put($storagePath, $content)) {
                    $changeOrder->photos()->create([
                        'uploaded_by' => $uploadedBy,
                        'original_name' => $originalName,
                        'path' => $storagePath,
                        'mime_type' => $mime,
                        'size_bytes' => $size,
                    ]);
                }
            }
        }

        if ($addToChangeOrderPhotos && $changeOrder->photos()->count() < ChangeOrder::MAX_PHOTOS) {
            $addPath = 'change-orders/'.$changeOrder->getKey().'/'.Str::uuid().'.'.$extension;
            if ($disk->put($addPath, $content)) {
                $changeOrder->photos()->create([
                    'uploaded_by' => $uploadedBy,
                    'original_name' => $originalName,
                    'path' => $addPath,
                    'mime_type' => $mime,
                    'size_bytes' => $size,
                ]);
            }
        }
    }
}
