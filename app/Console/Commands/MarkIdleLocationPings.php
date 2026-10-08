<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Tracking\Enums\TrackingStatus;
use App\Domains\Tracking\Models\LocationPing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MarkIdleLocationPings extends Command
{
    protected $signature = 'tracking:mark-idle';

    protected $description = 'Mark pings idle when no fresh fix has arrived within 5 minutes';

    public function handle(): int
    {
        $updated = LocationPing::query()
            ->where('pinged_at', '<', now()->subMinutes(5))
            ->where('status', TrackingStatus::ACTIVE->value)
            ->update(['status' => TrackingStatus::IDLE->value]);

        Log::info('Tracking idle sweep completed', ['updated' => $updated]);

        return self::SUCCESS;
    }
}
