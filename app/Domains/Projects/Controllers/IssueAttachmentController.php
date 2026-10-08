<?php

declare(strict_types=1);

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Requests\StoreIssueAttachmentsRequest;
use App\Domains\Projects\Services\IssueAttachmentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Post-create editing of an issue's photo/video attachments: add and remove.
 * "Replace" is delete + add on the client — no separate endpoint needed.
 */
final class IssueAttachmentController extends Controller
{
    public function __construct(private readonly IssueAttachmentService $attachmentService) {}

    public function store(StoreIssueAttachmentsRequest $request, Project $project, IssueHistory $issue): RedirectResponse
    {
        abort_unless($issue->project_id === $project->id, 404);

        $this->attachmentService->attach($issue, $request->attachments(), IssueHistory::MEDIA_COLLECTION);

        return redirect()->route('projects.issues.index', $project)->with('success', 'Media added to issue.');
    }

    public function destroy(Project $project, IssueHistory $issue, Media $media): RedirectResponse
    {
        $this->authorize('manageMedia', [$issue, $project]);
        abort_unless($issue->project_id === $project->id, 404);
        abort_unless(
            $media->model_id === $issue->getKey() && $media->collection_name === IssueHistory::MEDIA_COLLECTION,
            404
        );

        $media->delete();

        return redirect()->route('projects.issues.index', $project)->with('success', 'Attachment removed.');
    }
}
