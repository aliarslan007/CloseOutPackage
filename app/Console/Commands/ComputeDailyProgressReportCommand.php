<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Projects\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ComputeDailyProgressReportCommand extends Command
{
    protected $signature = 'cop:compute-daily-progress-report';

    protected $description = 'Store a daily snapshot of project and package activity for admin reporting';

    public function handle(): int
    {
        $snapshot = [
            'new_projects' => Project::query()->whereDate('created_at', today())->count(),
            'submitted_packages' => CloseoutPackage::query()->whereDate('submitted_at', today())->count(),
            'approved_packages' => CloseoutPackage::query()->whereDate('approved_at', today())->count(),
        ];

        activity('system')
            ->event('report.daily')
            ->withProperties($snapshot)
            ->log('Daily progress report snapshot');

        Log::info('Daily progress report computed', $snapshot);

        return self::SUCCESS;
    }
}
