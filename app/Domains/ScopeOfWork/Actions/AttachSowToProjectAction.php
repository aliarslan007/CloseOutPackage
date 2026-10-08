<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Actions;

use App\Domains\Projects\Models\Project;
use App\Domains\ScopeOfWork\DTOs\AttachSowToProjectDTO;
use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Exceptions\MarketAccessDeniedException;

final class AttachSowToProjectAction
{
    public function execute(SowReport $report, AttachSowToProjectDTO $dto): SowReport
    {
        if ($report->status !== SowStatus::DRAFT) {
            throw new InvalidStateTransitionException('Only draft SOWs can be linked to a project.');
        }

        $project = Project::query()->findOrFail($dto->projectId);

        if ($project->market_id !== $report->market_id) {
            throw new MarketAccessDeniedException('The selected project belongs to a different market.');
        }

        $data = ['project_id' => $project->id];

        // §4.3 — sync address/city/county from the project when the SOW fields are blank.
        foreach (['site_address' => 'address', 'site_city' => 'city', 'site_county' => 'county'] as $sowColumn => $projectColumn) {
            if (blank($report->{$sowColumn}) && $project->{$projectColumn} !== null) {
                $data[$sowColumn] = $project->{$projectColumn};
            }
        }

        $report->update($data);

        return $report;
    }
}
