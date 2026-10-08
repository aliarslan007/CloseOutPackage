<?php

declare(strict_types=1);

namespace App\Domains\Email\Actions;

use App\Domains\Email\Enums\EmailType;
use App\Domains\Email\Models\EmailLog;
use App\Domains\Users\Models\User;

/**
 * Bulk-removes Customer Emails outbox rows. Admins may prune any outbox
 * row; managers are scoped to rows in their managed projects — the same
 * set the outbox list (and EmailLogPolicy row actions) show them.
 *
 * Scoped to outbox-actionable types so this endpoint can never purge
 * internal/staff delivery rows.
 */
final class BulkDeleteCustomerEmailsAction
{
    /**
     * @param  list<int>  $ids
     */
    public function execute(User $actor, array $ids): int
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return 0;
        }

        $query = EmailLog::query()
            ->whereKey($ids)
            ->whereIn('type', array_map(fn (EmailType $t): string => $t->value, EmailType::manualTypes()));

        if (! $actor->hasRole('admin')) {
            $query->whereIn('project_id', $actor->managedProjects()->pluck('projects.id'));
        }

        return $query->delete();
    }
}
