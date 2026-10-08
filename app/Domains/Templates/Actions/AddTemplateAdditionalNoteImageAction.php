<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Templates\Models\Template;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class AddTemplateAdditionalNoteImageAction
{
    public function execute(Template $template, UploadedFile $file): Media
    {
        return $template
            ->addMedia($file)
            ->toMediaCollection(Template::ADDITIONAL_NOTES_COLLECTION);
    }
}
