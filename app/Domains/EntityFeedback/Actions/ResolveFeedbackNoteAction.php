<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Actions;

use App\Domains\EntityFeedback\DTOs\ResolveFeedbackNoteDTO;
use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Services\ImageCompressionService;
use Illuminate\Support\Facades\DB;

final class ResolveFeedbackNoteAction
{
    public function __construct(
        private readonly ApplyChangeOrderFeedbackPhotoAction $changeOrderPhotos,
        private readonly ImageCompressionService $compressionService = new ImageCompressionService,
    ) {}

    public function execute(EntityFeedbackNote $note, ResolveFeedbackNoteDTO $dto): EntityFeedbackNote
    {
        /** @var FeedbackRoundStatus $roundStatus */
        $roundStatus = $note->round->status;

        if (! in_array($roundStatus, [FeedbackRoundStatus::SUBMITTED, FeedbackRoundStatus::RESOLVED], true)) {
            throw new InvalidStateTransitionException(
                'Notes can only be resolved once customer feedback has been submitted.'
            );
        }

        // The customer withdrew this issue - there is nothing to resolve.
        if ($note->isWithdrawn()) {
            throw new InvalidStateTransitionException(
                'This issue was withdrawn by the customer, so it cannot be resolved.'
            );
        }

        // Rewriting an answer is allowed for the same short window as editing a
        // message: long enough to fix a typo, short enough that the customer's
        // copy of what was promised does not change under them a day later.
        if ($note->manager_response !== null && ! $note->answerEditableBy($dto->resolvedBy)) {
            throw new InvalidStateTransitionException(
                'Replies can only be edited for '.EntityFeedbackNote::editWindowMinutes()
                    .' minutes after they are written.'
            );
        }

        return DB::transaction(function () use ($note, $dto): EntityFeedbackNote {
            $note->update([
                'resolved_at' => $note->resolved_at ?? now(),
                'resolved_by' => $note->resolved_by ?? $dto->resolvedBy,
                'manager_response' => $dto->managerResponse,
                'response_sent_at' => $note->response_sent_at ?? now(),
            ]);

            if ($dto->replacementImageFile !== null) {
                $replacementFile = $this->compressionService->compressUploadedFile($dto->replacementImageFile);

                $this->changeOrderPhotos->execute(
                    round: $note->round,
                    file: $replacementFile,
                    replaceInChangeOrder: $dto->replaceInChangeOrder,
                    addToChangeOrderPhotos: $dto->addToChangeOrderPhotos,
                    sourcePhotoId: $note->source_photo_id,
                    replyToNoteId: $note->reply_to_note_id,
                    uploadedBy: $dto->resolvedBy,
                );

                $media = $note->addMedia($replacementFile)
                    ->usingFileName('replacement-feedback-'.$note->id.'.jpg')
                    ->usingName('Replacement Image')
                    ->toMediaCollection(EntityFeedbackNote::REPLACEMENT_COLLECTION);

                $note->update(['replacement_media_id' => $media->id]);
            }

            return $note->fresh(['annotatedMedia', 'replacementMedia', 'resolvedByUser']);
        });
    }
}
