<?php

declare(strict_types=1);

namespace App\Domains\Dashboard\Controllers;

use App\Domains\Dashboard\DTOs\DashboardStatsDTO;
use App\Domains\Dashboard\Services\DashboardQueryService;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardPackagesController extends Controller
{
    public function __construct(private readonly DashboardQueryService $service) {}

    public function __invoke(): Response
    {
        $this->authorize('view', DashboardStatsDTO::class);

        /** @var string|null $status */
        $status = request()->query('status');

        return Inertia::render('Dashboard/Packages', [
            'packages' => $this->service->packagesFor($this->user(), $status),
            'status' => $status,
        ]);
    }
}
