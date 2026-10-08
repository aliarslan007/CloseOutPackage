<?php

declare(strict_types=1);

namespace App\Domains\Projects\Actions;

use App\Domains\Projects\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class UploadRecDocumentAction
{
    public function execute(Project $project, UploadedFile $file): Media
    {
        return DB::transaction(function () use ($project, $file): Media {
            // Snapshot the uploader's name on the media itself — the audit trail
            // survives even if the user is later renamed or removed.
            $uploader = Auth::user();
            $media = $project->addMedia($file)
                ->withCustomProperties(['uploaded_by' => $uploader ? $uploader->name : 'Unknown'])
                ->toMediaCollection(Project::REC_DOCUMENTS_COLLECTION);

            activity('media')
                ->causedBy($uploader)
                ->performedOn($project)
                ->event('rec_document.uploaded')
                ->withProperties(['media_id' => $media->id, 'file_name' => $media->file_name])
                ->log('REC document uploaded');

            return $media;
        });
    }
}
