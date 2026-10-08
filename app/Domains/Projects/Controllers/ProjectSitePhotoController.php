<?php

declare(strict_types=1);

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Actions\UploadSitePhotoAction;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Queries\ProjectQuery;
use App\Domains\Projects\Requests\StoreSitePhotoRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectSitePhotoController extends Controller
{
    public function __construct(
        private readonly UploadSitePhotoAction $uploadSitePhotoAction,
        private readonly ProjectQuery $projectQuery,
    ) {}

    public function store(StoreSitePhotoRequest $request, Project $project): RedirectResponse
    {
        $file = $request->file('photo');

        if ($file === null) {
            return redirect()->route('projects.show', $project)->with('error', 'No photo provided.');
        }

        $this->uploadSitePhotoAction->execute($project, $file);

        return redirect()->route('projects.show', $project)->with('success', 'Site photo uploaded.');
    }

    public function show(Project $project): BinaryFileResponse
    {
        $this->authorize('view', $project);

        $media = $this->projectQuery->sitePhoto($project);

        abort_unless($media !== null, 404);

        return response()->file($media->getPath());
    }
}
