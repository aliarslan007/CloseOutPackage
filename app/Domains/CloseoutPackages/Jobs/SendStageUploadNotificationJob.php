<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Jobs;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\CloseoutPackages\Notifications\StageUploadedNotification;
use App\Domains\Projects\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendStageUploadNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly CloseoutPackage $package,
        public readonly PackageStage $stage,
    ) {}

    public function handle(): void
    {
        $this->package->load('project.manager');

        /** @var Project $project */
        $project = $this->package->project;
        $manager = $project->manager;

        if ($manager === null) {
            return;
        }

        $manager->notify(new StageUploadedNotification($this->package, $this->stage));
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Stage upload notification failed permanently', [
            'package_id' => $this->package->id,
            'stage_id' => $this->stage->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
