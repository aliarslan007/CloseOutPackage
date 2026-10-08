<?php

declare(strict_types=1);

namespace App\Domains\Customers\Actions;

use App\Domains\Customers\Models\CustomerEngineer;
use App\Domains\Projects\Models\Project;
use Illuminate\Support\Facades\DB;

final class AssignEngineerToProjectAction
{
    /**
     * Assign (or clear) the project's customer engineer of record.
     *
     * The legacy `vzw_engineer_*` snapshot columns are kept in sync so PDF
     * generation, SOW reports and email templates keep working unchanged.
     * Customer Portal access is deliberately NOT touched here: engineers have
     * no portal login until an admin explicitly creates one.
     */
    public function execute(Project $project, ?CustomerEngineer $engineer): Project
    {
        return DB::transaction(function () use ($project, $engineer): Project {
            if ($engineer === null) {
                $project->update(['customer_engineer_id' => null]);

                return $project->refresh();
            }

            $project->update([
                'customer_engineer_id' => $engineer->id,
                'customer_id' => $engineer->customer_id,
                'vzw_engineer_name' => $engineer->name,
                'vzw_engineer_email' => $engineer->email,
                'vzw_engineer_phone' => $engineer->phone,
            ]);

            return $project->refresh();
        });
    }
}
