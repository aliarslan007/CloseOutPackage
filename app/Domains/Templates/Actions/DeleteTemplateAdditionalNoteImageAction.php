<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class DeleteTemplateAdditionalNoteImageAction
{
    public function execute(Media $media): void
    {
        $media->delete();
    }
}
