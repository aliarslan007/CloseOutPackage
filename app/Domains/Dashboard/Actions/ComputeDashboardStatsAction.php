<?php

declare(strict_types=1);

namespace App\Domains\Dashboard\Actions;

use App\Domains\Dashboard\DTOs\DashboardStatsDTO;
use App\Domains\Dashboard\Services\DashboardQueryService;
use App\Domains\Users\Models\User;

final class ComputeDashboardStatsAction
{
    public function __construct(private readonly DashboardQueryService $queryService) {}

    public function execute(User $user): DashboardStatsDTO
    {
        return $this->queryService->statsFor($user);
    }
}
