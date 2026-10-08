<?php

declare(strict_types=1);

namespace App\Domains\Dashboard\Controllers;

use App\Domains\Dashboard\Actions\ComputeDashboardStatsAction;
use App\Domains\Dashboard\DTOs\DashboardStatsDTO;
use App\Domains\Dashboard\Services\DashboardQueryService;
use App\Http\Controllers\Controller;
use App\Shared\Enums\Role;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ComputeDashboardStatsAction $computeStatsAction,
        private readonly DashboardQueryService $queryService,
    ) {}

    public function index(): Response
    {
        $this->authorize('view', DashboardStatsDTO::class);

        // Customers can see the overview in their workspace, but not activity logs.
        $tab = $this->user()->hasRole(Role::CUSTOMER->value) ? null : request()->query('tab');

        /** @var string|null $showAllParam */
        $showAllParam = request()->query('activities_all');
        $showAll = $showAllParam === '1' && Gate::allows('viewAllActivities', DashboardStatsDTO::class);

        $canViewActivities = Gate::allows('viewActivities', DashboardStatsDTO::class);

        return Inertia::render('Dashboard', [
            'stats' => $this->computeStatsAction->execute($this->user()),
            'activities' => $canViewActivities ? $this->queryService->activityLogsFor($this->user(), $showAll) : null,
            'currentTab' => $tab === 'activity' ? 'activity' : 'overview',
        ]);
    }
}
