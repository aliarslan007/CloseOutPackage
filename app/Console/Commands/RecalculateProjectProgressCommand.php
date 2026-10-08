<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Projects\Actions\UpdateProjectProgressAction;
use App\Domains\Projects\Models\Project;
use Illuminate\Console\Command;

final class RecalculateProjectProgressCommand extends Command
{
    protected $signature = 'cop:recalculate-project-progress {project? : Optional ID of the specific project to recalculate}';

    protected $description = 'Recalculate progress percentage and status for projects';

    public function handle(UpdateProjectProgressAction $updateProjectProgress): int
    {
        $projectId = $this->argument('project');

        if ($projectId !== null) {
            $project = Project::query()->find($projectId);

            if ($project === null) {
                $this->error("Project [{$projectId}] not found.");

                return self::FAILURE;
            }

            $updateProjectProgress->execute($project);
            $project->refresh();
            $this->info("Recalculated project [{$project->id}] ({$project->name}): {$project->progress_percentage}% ({$project->status->value}).");

            return self::SUCCESS;
        }

        $count = 0;
        Project::query()->chunkById(100, function ($projects) use ($updateProjectProgress, &$count): void {
            foreach ($projects as $project) {
                $updateProjectProgress->execute($project);
                $count++;
            }
        });

        $this->info("Recalculated progress for {$count} project(s).");

        return self::SUCCESS;
    }
}
