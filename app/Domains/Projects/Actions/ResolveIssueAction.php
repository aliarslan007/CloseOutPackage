<?php

declare(strict_types=1);

namespace App\Domains\Projects\Actions;

use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Users\Models\User;

final class ResolveIssueAction
{
    public function execute(IssueHistory $issue, ?User $resolvedBy = null): IssueHistory
    {
        $issue->update([
            'resolved_at' => now(),
            'resolved_by' => $resolvedBy?->id,
        ]);

        return $issue;
    }
}
