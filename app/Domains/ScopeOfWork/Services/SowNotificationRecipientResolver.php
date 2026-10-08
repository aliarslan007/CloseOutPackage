<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Services;

use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class SowNotificationRecipientResolver
{
    /**
     * Resolves market managers and managing staff who should receive SOW workflow alerts.
     *
     * @return Collection<int, User>
     */
    public function resolveManagersFor(SowReport $report, ?User $excludeUser = null): Collection
    {
        $report->loadMissing(['project', 'market']);

        /** @var Collection<int, User> $recipients */
        $recipients = collect();

        // 1. If linked to a project with an assigned manager
        if ($report->project !== null && $report->project->manager_id !== null) {
            $manager = User::find($report->project->manager_id);
            if ($manager !== null) {
                $recipients->push($manager);
            }
        }

        // 2. Managers assigned to this market, or unassigned managers with global access
        $marketManagers = User::role(Role::MANAGER->value)
            ->where(function (Builder $query) use ($report): void {
                $query->whereHas('markets', function (Builder $mq) use ($report): void {
                    $mq->where('markets.id', $report->market_id);
                })->orWhereDoesntHave('markets');
            })
            ->get();

        $recipients = $recipients->concat($marketManagers);

        // 3. Fallback to admins if no managers were found
        if ($recipients->isEmpty()) {
            $recipients = User::role(Role::ADMIN->value)->get();
        }

        return $recipients
            ->unique('id')
            ->when(
                $excludeUser !== null,
                fn (Collection $col) => $col->reject(fn (User $user): bool => $user->id === $excludeUser->id)
            )
            ->values();
    }

    /**
     * Resolves the field technician (creator) of the SOW report.
     */
    public function resolveTechnicianFor(SowReport $report, ?User $excludeUser = null): ?User
    {
        $creator = $report->relationLoaded('creator') ? $report->creator : $report->creator()->first();

        if ($creator === null) {
            return null;
        }

        if ($excludeUser !== null && $creator->id === $excludeUser->id) {
            return null;
        }

        return $creator;
    }
}
