<?php

declare(strict_types=1);

namespace App\Domains\Email\Controllers;

use App\Domains\Email\Actions\DeleteEmailLogsAction;
use App\Domains\Email\Models\EmailLog;
use App\Domains\Email\Queries\EmailLogQuery;
use App\Domains\Email\Requests\DestroyEmailLogRequest;
use App\Domains\Email\Requests\IndexEmailLogRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmailLogController extends Controller
{
    public function __construct(
        private readonly EmailLogQuery $emailLogQuery,
        private readonly DeleteEmailLogsAction $deleteEmailLogsAction,
    ) {}

    public function index(IndexEmailLogRequest $request): Response
    {
        /** @var array{search?: ?string, date_from?: ?string, date_to?: ?string, sort?: ?string} $filters */
        $filters = $request->validated();
        $logs = $this->emailLogQuery->forAdmin($filters);

        return Inertia::render('Admin/EmailLogs/Index', [
            'logs' => $logs->items(),
            'pagination' => EmailLogQuery::paginatorMeta($logs),
            'filters' => $filters,
            // Drives whether the page renders the selection column and the
            // delete action, so the UI cannot offer what the policy denies.
            'can_delete' => Gate::allows('delete', EmailLog::class),
        ]);
    }

    public function destroy(DestroyEmailLogRequest $request): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = $request->validated('ids');

        $deleted = $this->deleteEmailLogsAction->execute($ids);

        return back()->with('success', "Deleted {$deleted} email log(s).");
    }
}
