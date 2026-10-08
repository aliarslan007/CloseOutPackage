<?php

declare(strict_types=1);

namespace App\Domains\Users\Listeners;

use App\Domains\Users\Contracts\ShouldSyncUserToSso;
use App\Domains\Users\Jobs\SyncUserToSsoJob;
use App\Domains\Users\Services\SsoUserSyncService;

class SyncUserToSsoListener
{
    public function __construct(private readonly SsoUserSyncService $service) {}

    public function handle(ShouldSyncUserToSso $event): void
    {
        SyncUserToSsoJob::dispatch($this->service->payloadFor($event));
    }
}
