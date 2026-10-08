<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Listeners;

use App\Domains\PdfGeneration\Events\PdfGenerationCompleted;
use App\Domains\PdfGeneration\Notifications\PdfReadyNotification;
use App\Domains\Projects\Models\Project;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyManagerOnPdfGenerationCompleted implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(PdfGenerationCompleted $event): void
    {
        $package = $event->revision->package()->with('project.manager')->firstOrFail();

        /** @var Project $project */
        $project = $package->project;
        $manager = $project->manager;

        if ($manager === null) {
            return;
        }

        $manager->notify(new PdfReadyNotification($event->revision));
    }
}
