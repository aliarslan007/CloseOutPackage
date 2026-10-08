<?php

declare(strict_types=1);

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Actions\UploadProjectDocumentAction;
use App\Domains\Projects\Controllers\Concerns\RespondsWithDocumentCollection;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Presenters\ProjectDocumentPresenter;
use App\Domains\Projects\Requests\StoreProjectDocumentRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Project Documents Hub uploads/downloads for the five categories that do not
 * have a dedicated controller. REC keeps {@see RecDocumentController} and its
 * existing routes.
 *
 * Upload/remove: admin or the assigned manager. View/download: anyone who can
 * view the project — which includes portal-scoped customers (ProjectPolicy::view).
 */
final class ProjectDocumentController extends Controller
{
    use RespondsWithDocumentCollection;

    public function __construct(
        private readonly UploadProjectDocumentAction $uploadProjectDocument,
        private readonly ProjectDocumentPresenter $projectDocumentPresenter,
    ) {}

    public function store(StoreProjectDocumentRequest $request, Project $project, string $collection): RedirectResponse|JsonResponse
    {
        $resolved = $this->resolveCollection($collection);

        $this->uploadProjectDocument->execute($project, $request->file('document'), $resolved);

        // XHR uploads (the hub's uploader) get just this collection back so the
        // client never has to re-render the whole project page after a file.
        if ($request->wantsJson()) {
            return $this->documentCollectionJson($project, $resolved, $this->projectDocumentPresenter);
        }

        return redirect()->back()->with('success', 'Document uploaded.');
    }

    public function show(Project $project, string $collection, Media $media): BinaryFileResponse
    {
        $this->authorize('view', $project);
        $this->ensureBelongsToProject($project, $media, $this->resolveCollection($collection));

        // These categories are documents only (no images), so there is no
        // thumbnail conversion to serve — the original is always the file.
        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$media->file_name.'"',
        ]);
    }

    public function download(Project $project, string $collection, Media $media): BinaryFileResponse
    {
        $this->authorize('view', $project);
        $this->ensureBelongsToProject($project, $media, $this->resolveCollection($collection));

        return response()->download($media->getPath(), $media->file_name);
    }

    public function destroy(Request $request, Project $project, string $collection, Media $media): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);
        $resolved = $this->resolveCollection($collection);
        $this->ensureBelongsToProject($project, $media, $resolved);

        $media->delete();

        if ($request->wantsJson()) {
            return $this->documentCollectionJson($project, $resolved, $this->projectDocumentPresenter);
        }

        return redirect()->back()->with('success', 'Document removed.');
    }

    /**
     * Resolve the route slug to its media collection, rejecting unknown slugs
     * and REC (which keeps its own controller and routes) with a 404.
     */
    private function resolveCollection(string $slug): string
    {
        $collection = array_search($slug, Project::DOCUMENT_COLLECTIONS, true);

        abort_if($collection === false || $collection === Project::REC_DOCUMENTS_COLLECTION, 404);

        return $collection;
    }

    private function ensureBelongsToProject(Project $project, Media $media, string $collection): void
    {
        abort_unless(file_exists($media->getPath()), 404);
        abort_unless(
            $media->collection_name === $collection
                && $media->model_type === Project::class
                && $media->model_id === $project->getKey(),
            404
        );
    }
}
