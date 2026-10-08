<?php

declare(strict_types=1);

namespace App\Domains\Projects\Actions;

use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Sets (or clears) the single primary field technician on a project.
 *
 * The caller is responsible for keeping the primary inside the project's
 * assigned supervisors — see ProjectController::syncPrimarySupervisor() and
 * RemoveFieldSupervisorAction.
 */
final class SetPrimarySupervisorAction
{
    public function execute(Project $project, ?int $supervisorId, ?User $actor = null): void
    {
        $actor ??= Auth::user();

        $currentId = $project->primary_supervisor_id === null ? null : (int) $project->primary_supervisor_id;

        if ($currentId === $supervisorId) {
            return;
        }

        DB::transaction(function () use ($project, $supervisorId, $actor): void {
            $project->update(['primary_supervisor_id' => $supervisorId]);

            activity('project')
                ->causedBy($actor)
                ->performedOn($project)
                ->event('supervisor.primary')
                ->withProperties(['user_id' => $supervisorId])
                ->log($supervisorId === null ? 'Primary field supervisor cleared' : 'Primary field supervisor set');
        });
    }
}
