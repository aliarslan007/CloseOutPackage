<?php

declare(strict_types=1);

namespace App\Domains\Email\Services;

use App\Domains\Projects\Models\Project;

final class EmailSubjectBuilderService
{
    public function build(Project $project, string $description): string
    {
        $market = $project->market()->first();

        return $project->name.' → '.($market !== null ? $market->name : 'Unknown Market').' → '.$description;
    }
}
