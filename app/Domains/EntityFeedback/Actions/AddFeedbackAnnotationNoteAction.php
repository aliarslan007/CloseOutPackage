<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Actions;

use App\Domains\EntityFeedback\DTOs\AddFeedbackAnnotationNoteDTO;
use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\EntityFeedback\Notifications\FeedbackFollowUpNotification;
use App\Domains\EntityFeedback\Services\FeedbackEntityResolver;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Services\ImageCompressionService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

final class AddFeedbackAnnotationNoteAction
{
    public function __construct(
        private readonly FeedbackEntityResolver $entities,
        private readonly ApplyChangeOrderFeedbackPhotoAction $changeOrderPhotos,
        private readonly ImageCompressionService $compressionService = new ImageCompressionService,
    ) {}

    public function execute(EntityFeedbackRound $round, AddFeedbackAnnotationNoteDTO $dto): EntityFeedbackNote
    {
        // Enforced here too, not only in the policy: the emailed-token path
        // authorizes on the token and never reaches the policy.
        if ($this->entities->entityIsDecided($round)) {
            throw new InvalidStateTransitionException(
                'This change order has been decided, so its review is closed.'
            );
        }

        // The customer may keep writing on their draft, staff and customer can
        // converse during submitted review, or push back on a resolution.
        if (! $round->isOpen() && ! $round->isSubmitted() && ! $round->isResolved()) {
            throw new InvalidStateTransitionException(
                'Feedback can only be added to an active review round.'
            );
        }

        // A follow-up on a resolved round means the conversation is not over:
        // it goes back to the manager, and the answers already published stay
        // published because they were stamped when they were sent.
        $reopened = $round->isResolved();

        return DB::transaction(function () use ($round, $dto, $reopened): EntityFeedbackNote {
            $author = $dto->createdBy !== null ? User::query()->find($dto->createdBy) : null;
            $isStaff = $author?->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value, Role::FIELD_SUPERVISOR->value]) ?? false;

            // The customer dropped this issue, so the conversation is closed to
            // everybody - neither staff nor the customer can keep writing on it.
            // A new ask means a new issue, not a resurrection of the withdrawn one.
            if ($this->withdrawnThreadNotes($round, $dto)->exists()) {
                throw new InvalidStateTransitionException(
                    'This issue was withdrawn, so its conversation is closed.'
                );
            }

            /** @var EntityFeedbackNote $note */
            $note = $round->notes()->create([
                'entity_type' => $dto->entityType,
                'entity_id' => $dto->entityId,
                // A second comment on a photo continues that photo's
                // conversation rather than starting a detached one.
                'reply_to_note_id' => $this->resolveReplyTarget($round, $dto),
                'source_photo_id' => $dto->sourcePhotoId,
                'source_photo_type' => $dto->sourcePhotoType,
                'requested_action' => $dto->requestedAction,
                'original_image_url' => $dto->originalImageUrl,
                'annotation_layer' => $dto->annotationLayer,
                'body' => $dto->body,
                'created_by' => $dto->createdBy,
                'response_sent_at' => $isStaff ? now() : null,
            ]);

            if ($reopened) {
                $roundUpdates = [
                    'status' => FeedbackRoundStatus::SUBMITTED,
                    'resolved_at' => null,
                    'resolved_by' => null,
                ];

                if (! $isStaff) {
                    $roundUpdates['manager_acknowledged_at'] = null;
                    $roundUpdates['manager_acknowledged_by'] = null;
                }

                $round->update($roundUpdates);
            }

            if ($isStaff && $round->manager_acknowledged_at === null) {
                $round->update([
                    'manager_acknowledged_at' => now(),
                    'manager_acknowledged_by' => $dto->createdBy,
                ]);
            }

            if ($dto->annotatedImageFile !== null) {
                $media = $note->addMedia($dto->annotatedImageFile)
                    ->usingFileName('feedback-annotation-'.$note->id.'.jpg')
                    ->usingName('Annotated Feedback Image')
                    ->toMediaCollection(EntityFeedbackNote::MEDIA_COLLECTION);

                $note->update(['annotated_media_id' => $media->id]);
            }

            if ($dto->replacementImageFile !== null) {
                $replacementFile = $this->compressionService->compressUploadedFile($dto->replacementImageFile);

                $this->changeOrderPhotos->execute(
                    round: $round,
                    file: $replacementFile,
                    replaceInChangeOrder: $dto->replaceInChangeOrder,
                    addToChangeOrderPhotos: $dto->addToChangeOrderPhotos,
                    sourcePhotoId: $dto->sourcePhotoId,
                    replyToNoteId: $dto->replyToNoteId,
                    uploadedBy: $dto->createdBy,
                );

                $media = $note->addMedia($replacementFile)
                    ->usingFileName('feedback-replacement-'.$note->id.'.jpg')
                    ->usingName('Replacement Photo')
                    ->toMediaCollection(EntityFeedbackNote::REPLACEMENT_COLLECTION);

                $note->update(['replacement_media_id' => $media->id]);
            }

            $this->notifyManagerOfFollowUp($round, $dto, $reopened);

            return $note->fresh(['annotatedMedia', 'replacementMedia', 'author']);
        });
    }

    /**
     * Where a new note sits in its conversation. An explicit target wins,
     * otherwise it continues the most recent note on the same photo, so the
     * thread reads in order without the client having to track it.
     */
    private function resolveReplyTarget(EntityFeedbackRound $round, AddFeedbackAnnotationNoteDTO $dto): ?int
    {
        if ($dto->replyToNoteId !== null) {
            return $round->notes()->whereKey($dto->replyToNoteId)->exists()
                ? $dto->replyToNoteId
                : null;
        }

        if ($dto->sourcePhotoId === null) {
            return null;
        }

        return $round->notes()
            ->where('source_photo_id', $dto->sourcePhotoId)
            ->where('source_photo_type', $dto->sourcePhotoType)
            ->orderByDesc('id')
            ->value('id');
    }

    /**
     * The withdrawn messages that make up the conversation this message belongs
     * to. The pairing is the same one resolveReplyTarget() files the message
     * under, so a closed conversation stays closed - never a neighbour with a
     * colliding photo/category id, which is still free to be written on.
     *
     * @return HasMany<EntityFeedbackNote>
     */
    private function withdrawnThreadNotes(EntityFeedbackRound $round, AddFeedbackAnnotationNoteDTO $dto): HasMany
    {
        $notes = $round->notes()->whereNotNull('withdrawn_at');

        if ($dto->sourcePhotoId === null) {
            // A section message carries no photo: match on the id being null,
            // then on its type (a type-less section thread is its own thread).
            $notes->whereNull('source_photo_id');

            if ($dto->sourcePhotoType === null) {
                $notes->whereNull('source_photo_type');
            } else {
                $notes->where('source_photo_type', $dto->sourcePhotoType);
            }

            return $notes;
        }

        $notes->where('source_photo_id', $dto->sourcePhotoId);

        if ($dto->sourcePhotoType === null) {
            // ponytail: a message pinned to a photo but typed by nothing is the
            // same conversation as "photo" (build-feedback-threads.ts agrees).
            // Upgrade if a real typed-photo namespace joins it.
            $notes->where(function ($query): void {
                $query->whereNull('source_photo_type')->orWhere('source_photo_type', 'photo');
            });
        } else {
            $notes->where('source_photo_type', $dto->sourcePhotoType);
        }

        return $notes;
    }

    /**
     * Only a follow-up needs telling the manager about: the first submission
     * notifies through the submit action, which the customer must still press.
     */
    private function notifyManagerOfFollowUp(EntityFeedbackRound $round, AddFeedbackAnnotationNoteDTO $dto, bool $reopened): void
    {
        if (! $reopened) {
            return;
        }

        $manager = $this->entities->managerFor($round);
        $actor = $dto->createdBy !== null ? User::query()->find($dto->createdBy) : null;

        if ($manager === null) {
            return;
        }

        $manager->notify(new FeedbackFollowUpNotification($round, $actor ?? $manager));
    }
}
