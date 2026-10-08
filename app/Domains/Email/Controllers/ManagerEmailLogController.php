<?php

declare(strict_types=1);

namespace App\Domains\Email\Controllers;

use App\Domains\Email\Models\EmailLog;
use App\Domains\Email\Queries\EmailLogQuery;
use App\Domains\Email\Requests\IndexEmailLogRequest;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ManagerEmailLogController extends Controller
{
    public function __construct(private readonly EmailLogQuery $emailLogQuery) {}

    public function __invoke(IndexEmailLogRequest $request): Response
    {
        /** @var array{search?: ?string, date_from?: ?string, date_to?: ?string, sort?: ?string} $filters */
        $filters = $request->validated();
        $logs = $this->emailLogQuery->forManager($this->user(), $filters);

        return Inertia::render('Manager/EmailLogs/Index', [
            'logs' => $logs->items(),
            'pagination' => EmailLogQuery::paginatorMeta($logs),
            'filters' => $filters,
            // Read-only for managers; drives whether the page renders the
            // selection column and the delete action.
            'can_delete' => Gate::allows('delete', EmailLog::class),
        ]);
    }
}
