<?php

declare(strict_types=1);

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Actions\ResolveIssueAction;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class ResolveIssueController extends Controller
{
    public function __construct(private readonly ResolveIssueAction $resolveIssueAction) {}

    public function __invoke(Project $project, IssueHistory $issue): RedirectResponse
    {
        $this->authorize('resolve', [$issue, $project]);

        $this->resolveIssueAction->execute($issue, $this->user());

        return redirect()->route('projects.issues.index', $project)->with('success', 'Issue resolved.');
    }
}
