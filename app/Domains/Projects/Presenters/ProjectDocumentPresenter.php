<?php

declare(strict_types=1);

namespace App\Domains\Projects\Presenters;

use App\Domains\Projects\Models\Project;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Shapes a project's document collections for the Project Documents Hub.
 *
 * Iterates {@see Project::DOCUMENT_COLLECTIONS} so every category is emitted in
 * one unified shape. REC still serves through its dedicated routes while the
 * remaining categories route through ProjectDocumentController.
 */
final class ProjectDocumentPresenter
{
    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function present(Project $project): array
    {
        $documents = [];

        foreach (array_keys(Project::DOCUMENT_COLLECTIONS) as $collection) {
            $documents[$collection] = $this->presentCollection($project, $collection);
        }

        return $documents;
    }

    /**
     * A single collection, newest first — used by the upload/delete endpoints
     * so a single-file action never has to re-render every category.
     *
     * @return array<int, array<string, mixed>>
     */
    public function presentCollection(Project $project, string $collection): array
    {
        return $project->getMedia($collection)
            ->sortByDesc('created_at')
            ->map(fn (Media $media): array => $this->mapMedia($project, $media, $collection))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMedia(Project $project, Media $media, string $collection): array
    {
        $slug = Project::DOCUMENT_COLLECTIONS[$collection];
        $isRec = $collection === Project::REC_DOCUMENTS_COLLECTION;

        return [
            'id' => $media->id,
            'name' => $media->file_name,
            'mime_type' => $media->mime_type ?? 'application/octet-stream',
            'size_bytes' => $media->size,
            'uploaded_by' => $media->getCustomProperty('uploaded_by'),
            'uploaded_at' => $media->created_at?->toFormattedDateString(),
            'view_url' => $isRec
                ? route('projects.rec-documents.show', [$project, $media])
                : route('projects.documents.show', [$project, $slug, $media]),
            'download_url' => $isRec
                ? route('projects.rec-documents.download', [$project, $media])
                : route('projects.documents.download', [$project, $slug, $media]),
        ];
    }
}
