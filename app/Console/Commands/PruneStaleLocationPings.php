<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Tracking\Models\LocationPing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneStaleLocationPings extends Command
{
    protected $signature = 'tracking:prune-stale';

    protected $description = 'Delete location pings older than 24 hours to keep the table lean';

    public function handle(): int
    {
        $deleted = LocationPing::query()
            ->where('pinged_at', '<', now()->subDay())
            ->delete();

        Log::info('Tracking prune completed', ['deleted' => $deleted]);

        return self::SUCCESS;
    }
}
