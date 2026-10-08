<?php

declare(strict_types=1);

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Actions\UploadRecDocumentAction;
use App\Domains\Projects\Controllers\Concerns\RespondsWithDocumentCollection;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Presenters\ProjectDocumentPresenter;
use App\Domains\Projects\Requests\StoreRecDocumentRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * REC documents issued by the customer company (Verizon, AT&T, ...), tied to a project.
 * Upload/remove: admin or the assigned manager. View/download: anyone who can view
 * the project — which includes portal-scoped customers (ProjectPolicy::view).
 */
final class RecDocumentController extends Controller
{
    use RespondsWithDocumentCollection;

    public function __construct(
        private readonly UploadRecDocumentAction $uploadRecDocument,
        private readonly ProjectDocumentPresenter $projectDocumentPresenter,
    ) {}

    public function store(StoreRecDocumentRequest $request, Project $project): RedirectResponse|JsonResponse
    {
        $this->uploadRecDocument->execute($project, $request->file('document'));

        // The Project Documents Hub uploads REC through this controller too, so
        // it needs the same lightweight JSON reply as the generic endpoint.
        if ($request->wantsJson()) {
            return $this->documentCollectionJson(
                $project,
                Project::REC_DOCUMENTS_COLLECTION,
                $this->projectDocumentPresenter,
            );
        }

        return redirect()->back()->with('success', 'REC document uploaded.');
    }

    public function show(Request $request, Project $project, Media $media): BinaryFileResponse
    {
        $this->authorize('view', $project);
        $this->ensureBelongsToProject($project, $media);

        // ?preview=1 serves the generated `medium` thumbnail for card grids;
        // falls back to the original while the conversion is still queued.
        $path = $request->boolean('preview') && $media->hasGeneratedConversion('medium')
            ? $media->getPath('medium')
            : $media->getPath();

        return response()->file($path, [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$media->file_name.'"',
        ]);
    }

    public function download(Project $project, Media $media): BinaryFileResponse
    {
        $this->authorize('view', $project);
        $this->ensureBelongsToProject($project, $media);

        return response()->download($media->getPath(), $media->file_name);
    }

    public function destroy(Request $request, Project $project, Media $media): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);
        $this->ensureBelongsToProject($project, $media);

        $media->delete();

        if ($request->wantsJson()) {
            return $this->documentCollectionJson(
                $project,
                Project::REC_DOCUMENTS_COLLECTION,
                $this->projectDocumentPresenter,
            );
        }

        return redirect()->back()->with('success', 'REC document removed.');
    }

    private function ensureBelongsToProject(Project $project, Media $media): void
    {
        abort_unless(file_exists($media->getPath()), 404);
        abort_unless(
            $media->collection_name === Project::REC_DOCUMENTS_COLLECTION
                && $media->model_type === Project::class
                && $media->model_id === $project->getKey(),
            404
        );
    }
}
