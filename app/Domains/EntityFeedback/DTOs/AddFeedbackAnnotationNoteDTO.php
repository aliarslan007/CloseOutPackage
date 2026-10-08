<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\DTOs;

use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use Illuminate\Http\UploadedFile;

final class AddFeedbackAnnotationNoteDTO
{
    /**
     * @param  array<string, mixed>|null  $annotationLayer
     */
    public function __construct(
        public FeedbackEntityType $entityType,
        public int $entityId,
        public ?string $originalImageUrl,
        public string $body,
        public int $createdBy,
        public ?int $sourcePhotoId = null,
        public ?string $sourcePhotoType = null,
        public ?int $replyToNoteId = null,
        public ?string $requestedAction = null,
        public ?UploadedFile $annotatedImageFile = null,
        public ?UploadedFile $replacementImageFile = null,
        public ?array $annotationLayer = null,
        public bool $replaceInChangeOrder = false,
        public bool $addToChangeOrderPhotos = false,
    ) {}
}
