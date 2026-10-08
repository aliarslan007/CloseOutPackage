<?php

declare(strict_types=1);

namespace App\Domains\Projects\Actions;

use App\Domains\Projects\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Stores an uploaded document into any of the project's document collections.
 * The caller is responsible for validating that the collection is allowed.
 */
final class UploadProjectDocumentAction
{
    public function execute(Project $project, UploadedFile $file, string $collection): Media
    {
        return DB::transaction(function () use ($project, $file, $collection): Media {
            // Snapshot the uploader's name on the media itself — the audit trail
            // survives even if the user is later renamed or removed.
            $uploader = Auth::user();
            $media = $project->addMedia($file)
                ->withCustomProperties(['uploaded_by' => $uploader?->name ?? 'Unknown'])
                ->toMediaCollection($collection);

            activity('media')
                ->causedBy($uploader)
                ->performedOn($project)
                ->event('project_document.uploaded')
                ->withProperties(['collection' => $collection, 'media_id' => $media->id, 'file_name' => $media->file_name])
                ->log('Project document uploaded');

            return $media;
        });
    }
}
