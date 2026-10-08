<?php

declare(strict_types=1);

namespace App\Domains\Users\Jobs;

use App\Domains\Users\Services\SsoUserSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncUserToSsoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    /**
     * @param  array<string, string>  $payload
     */
    public function __construct(public readonly array $payload) {}

    public function handle(SsoUserSyncService $service): void
    {
        $service->sync($this->payload);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('SSO user sync failed permanently', [
            'user_uuid' => $this->payload['uuid'] ?? null,
            'action' => $this->payload['action'] ?? null,
            'error' => $exception?->getMessage(),
        ]);
    }
}
