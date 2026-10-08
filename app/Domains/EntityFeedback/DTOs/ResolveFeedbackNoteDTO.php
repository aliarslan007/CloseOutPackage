<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\DTOs;

use Illuminate\Http\UploadedFile;

final class ResolveFeedbackNoteDTO
{
    public function __construct(
        public int $resolvedBy,
        public ?string $managerResponse = null,
        public ?UploadedFile $replacementImageFile = null,
        public bool $replaceInChangeOrder = false,
        public bool $addToChangeOrderPhotos = false,
    ) {}
}
