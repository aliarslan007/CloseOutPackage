<?php

declare(strict_types=1);

namespace App\Domains\Projects\Actions;

use App\Domains\Projects\Models\Project;
use App\Shared\Services\ImageCompressionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class UploadSitePhotoAction
{
    public function __construct(
        private readonly ImageCompressionService $compressionService = new ImageCompressionService,
    ) {}

    public function execute(Project $project, UploadedFile $file): Media
    {
        return DB::transaction(function () use ($project, $file): Media {
            $fileToStore = $this->compressionService->compressUploadedFile($file);
            $media = $project->addMedia($fileToStore)->toMediaCollection(Project::SITE_PHOTO_COLLECTION);

            activity('media')
                ->causedBy(Auth::user())
                ->performedOn($project)
                ->event('site_photo.uploaded')
                ->withProperties(['media_id' => $media->id])
                ->log('Site photo uploaded');

            return $media;
        });
    }
}
