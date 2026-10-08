<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Requests\Concerns;

use App\Domains\EntityFeedback\DTOs\AddFeedbackAnnotationNoteDTO;
use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Domains\Photos\Models\Photo;
use Closure;
use Illuminate\Http\UploadedFile;

/**
 * Shared mapping for a note carried on a feedback request.
 *
 * A customer's first note is posted to the "open round" endpoint, because there
 * is no round to post to yet. Both requests therefore need to read the same
 * fields, and keeping one implementation stops the two from drifting.
 */
trait BuildsFeedbackNoteDto
{
    protected function feedbackNoteRules(): array
    {
        return [
            'original_image_url' => ['nullable', 'string', 'max:1000'],
            'body' => ['nullable', 'string', 'max:'.EntityFeedbackNote::MAX_BODY_LENGTH],
            'source_photo_id' => [
                'nullable',
                'integer',
                // A photo pin must point at a photo owned by the same entity;
                // otherwise a stale or tampered payload could plant feedback
                // from one package into another package's review.
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    $entityType = (string) $this->input('entity_type');
                    $entityId = (int) $this->input('entity_id');
                    $sourcePhotoType = (string) ($this->input('source_photo_type') ?? '');

                    // Only a photo-shaped pin points at a real Photo. A null,
                    // "section", or "category" id belongs to a different id
                    // space and must not be asked to be a Photo.
                    if (
                        $entityType === FeedbackEntityType::CLOSEOUT_PACKAGE->value &&
                        ($sourcePhotoType === '' || $sourcePhotoType === 'photo')
                    ) {
                        $exists = Photo::query()
                            ->whereKey((int) $value)
                            ->where('closeout_package_id', $entityId)
                            ->exists();

                        if (! $exists) {
                            $fail('The selected photo does not belong to this package.');
                        }
                    }
                },
            ],
            'source_photo_type' => ['nullable', 'string', 'max:100'],
            'requested_action' => ['nullable', 'string', 'max:50'],
            'reply_to_note_id' => ['nullable', 'integer'],
            'annotated_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,heic', 'max:10240'],
            'replacement_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,heic', 'max:10240'],
            'annotation_layer' => ['nullable'],
            'replace_in_change_order' => ['nullable', 'boolean'],
            'add_to_change_order_photos' => ['nullable', 'boolean'],
        ];
    }

    protected function buildFeedbackNoteDto(FeedbackEntityType $entityType, int $entityId): ?AddFeedbackAnnotationNoteDTO
    {
        $body = $this->validated('body');
        $hasBody = is_string($body) && trim($body) !== '';

        /** @var UploadedFile|null $annotatedFile */
        $annotatedFile = $this->file('annotated_image');

        /** @var UploadedFile|null $replacementFile */
        $replacementFile = $this->file('replacement_image');

        // No body and no attached images means this is a bare "open a round"
        // request (the manager pressing Open Review Round) and there is no note to create.
        if (! $hasBody && $annotatedFile === null && $replacementFile === null) {
            return null;
        }

        $noteBody = $hasBody
            ? trim((string) $body)
            : ($replacementFile !== null ? 'Attached replacement photo.' : 'Attached annotated photo.');

        $rawLayer = $this->validated('annotation_layer');
        $layer = null;

        if (is_string($rawLayer) && $rawLayer !== '') {
            $decoded = json_decode($rawLayer, true);

            if (is_array($decoded)) {
                $layer = $decoded;
            }
        } elseif (is_array($rawLayer)) {
            $layer = $rawLayer;
        }

        $sourcePhotoId = $this->validated('source_photo_id');
        $replyToNoteId = $this->validated('reply_to_note_id');
        $requestedAction = $this->validated('requested_action');

        return new AddFeedbackAnnotationNoteDTO(
            entityType: $entityType,
            entityId: $entityId,
            originalImageUrl: $this->validated('original_image_url'),
            body: $noteBody,
            createdBy: (int) $this->user()?->id,
            sourcePhotoId: $sourcePhotoId !== null ? (int) $sourcePhotoId : null,
            sourcePhotoType: $this->validated('source_photo_type'),
            replyToNoteId: $replyToNoteId !== null ? (int) $replyToNoteId : null,
            requestedAction: is_string($requestedAction) && $requestedAction !== '' ? $requestedAction : null,
            annotatedImageFile: $annotatedFile,
            replacementImageFile: $replacementFile,
            annotationLayer: $layer,
            replaceInChangeOrder: filter_var($this->validated('replace_in_change_order'), FILTER_VALIDATE_BOOLEAN),
            addToChangeOrderPhotos: filter_var($this->validated('add_to_change_order_photos'), FILTER_VALIDATE_BOOLEAN),
        );
    }
}
