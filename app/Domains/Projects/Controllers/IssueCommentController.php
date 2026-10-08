<?php

declare(strict_types=1);

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Actions\AddIssueCommentAction;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Requests\AddIssueCommentRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class IssueCommentController extends Controller
{
    public function __construct(private readonly AddIssueCommentAction $addIssueCommentAction) {}

    public function __invoke(AddIssueCommentRequest $request, Project $project, IssueHistory $issue): RedirectResponse
    {
        $this->addIssueCommentAction->execute($issue, $request->toDTO(), $this->user(), $request->attachments());

        return redirect()->route('projects.issues.index', $project)
            ->with('success', 'Comment added.');
    }
}
