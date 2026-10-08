<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Jobs;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Notifications\TemplateDriftNotification;
use App\Domains\CloseoutPackages\Services\TemplateDriftService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Re-checks a single draft package for template drift and notifies its creator
 * when the drift is unsafe. Carries only the package id so fan-out stays light.
 */
class NotifyPackageDriftJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 60, 120];

    public function __construct(public readonly int $packageId) {}

    public function handle(): void
    {
        $package = CloseoutPackage::with('creator')->find($this->packageId);

        if ($package === null) {
            return;
        }

        if ($package->status !== PackageStatus::DRAFT) {
            return;
        }

        $report = app(TemplateDriftService::class)->detect($package);

        if (! $report->hasUnsafeDrift) {
            return;
        }

        $package->creator?->notify(new TemplateDriftNotification($package, $report));
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Package drift notification failed permanently', [
            'package_id' => $this->packageId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
