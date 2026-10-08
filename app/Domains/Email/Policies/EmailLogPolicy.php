<?php

declare(strict_types=1);

namespace App\Domains\Email\Policies;

use App\Domains\Email\Models\EmailLog;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;

class EmailLogPolicy
{
    /**
     * Email logs are an audit trail: only admin and manager may read them.
     */
    public function viewAny(User $user): bool
    {
        return $this->canManageEmailLogs($user);
    }

    /**
     * The audit trail is evidence, so purging it is admin-only. Managers can
     * read the logs they are scoped to but never remove them.
     */
    public function delete(User $user): bool
    {
        return $user->hasRole(Role::ADMIN->value);
    }

    // Outbox actions: admins may act on every customer email; managers only
    // on rows inside their managed projects — the same scope the outbox list
    // shows them, so the UI can never offer what the policy denies.

    public function send(User $user, EmailLog $log): bool
    {
        return $this->canActOnOutboxRow($user, $log);
    }

    public function schedule(User $user, EmailLog $log): bool
    {
        return $this->canActOnOutboxRow($user, $log);
    }

    public function cancel(User $user, EmailLog $log): bool
    {
        return $this->canActOnOutboxRow($user, $log);
    }

    public function resend(User $user, EmailLog $log): bool
    {
        return $this->canActOnOutboxRow($user, $log);
    }

    public function retry(User $user, EmailLog $log): bool
    {
        return $this->canActOnOutboxRow($user, $log);
    }

    private function canActOnOutboxRow(User $user, EmailLog $log): bool
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

        if (! $user->hasRole(Role::MANAGER->value)) {
            return false;
        }

        return $log->project_id !== null
            && $user->managedProjects()->whereKey($log->project_id)->exists();
    }

    private function canManageEmailLogs(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]);
    }
}
