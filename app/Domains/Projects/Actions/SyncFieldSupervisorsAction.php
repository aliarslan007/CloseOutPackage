<?php

declare(strict_types=1);

namespace App\Domains\Projects\Actions;

use App\Domains\Projects\Events\FieldSupervisorAssigned;
use App\Domains\Projects\Events\FieldSupervisorRemoved;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;

final class SyncFieldSupervisorsAction
{
    /**
     * Replace the project's field supervisors and dispatch assignment events
     * only for the users that were actually added or removed.
     *
     * @param  array<int, int|string>  $supervisorIds
     */
    public function execute(Project $project, array $supervisorIds, ?User $actor = null): void
    {
        $cleanSupervisorIds = array_values(array_unique(array_map('intval', array_filter($supervisorIds, fn ($id) => is_numeric($id) && (int) $id > 0))));

        $existingIds = array_map('intval', $project->fieldSupervisors()->allRelatedIds()->all());

        $project->fieldSupervisors()->sync($cleanSupervisorIds);

        // Dropping the primary technician from the assignment list clears the
        // primary too, so the project never points at a non-member.
        $primaryId = $project->primary_supervisor_id === null ? null : (int) $project->primary_supervisor_id;

        if ($primaryId !== null && ! in_array($primaryId, $cleanSupervisorIds, true)) {
            $project->update(['primary_supervisor_id' => null]);
        }

        foreach (array_diff($cleanSupervisorIds, $existingIds) as $supervisorId) {
            $user = User::find($supervisorId);
            if ($user !== null) {
                FieldSupervisorAssigned::dispatch($project, $user, $actor);
            }
        }

        foreach (array_diff($existingIds, $cleanSupervisorIds) as $supervisorId) {
            $user = User::find($supervisorId);
            if ($user !== null) {
                FieldSupervisorRemoved::dispatch($project, $user, $actor);
            }
        }
    }
}
