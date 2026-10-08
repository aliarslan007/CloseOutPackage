<?php

declare(strict_types=1);

namespace App\Domains\Dashboard\Services;

use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Support\Str;

final class DashboardAttentionService
{
    /**
     * @param  array<string, int>  $counts
     * @return list<array{key: string, label: string, count: int, href: string, tone: string}>
     */
    public function attentionItems(User $user, array $counts): array
    {
        /** @var list<array{key: string, label: string, count: int, href: string, tone: string}> $items */
        $items = [];

        if ($user->hasRole(Role::ADMIN->value)) {
            $this->pushAttention($items, $counts['projectsNeedingManager'] ?? 0, 'Projects need a manager', route('projects.index', ['assignment' => 'unassigned']), 'danger');
            $this->pushAttention($items, $counts['packagesNeedingRework'] ?? 0, 'Packages need rework', route('dashboard.packages', ['status' => 'needs_rework']), 'danger');
            $this->pushAttention($items, $counts['projectsNeedingSupervisor'] ?? 0, 'Projects need a supervisor', route('projects.index', ['assignment' => 'needs_supervisor']), 'warning');
            $this->pushAttention($items, $counts['projectsStalled'] ?? 0, 'Projects stalled for '.Project::STALLED_ACTIVITY_DAYS.' days', route('projects.index', ['assignment' => 'stalled']), 'warning');
            $this->pushAttention($items, $counts['pendingApprovals'] ?? 0, 'Packages awaiting review', route('dashboard.packages', ['status' => 'awaiting_review']), 'warning');
            $this->pushAttention($items, $counts['changeOrdersPendingCustomer'] ?? 0, 'Change orders awaiting customer decision', route('change-orders.index', ['status' => 'customer_action_required']), 'warning');
            $this->pushAttention($items, $counts['packagesReadyToSubmit'] ?? 0, 'Packages ready to submit', route('dashboard.packages', ['status' => 'ready_to_submit']), 'info');
        } elseif ($user->hasRole(Role::MANAGER->value)) {
            $this->pushAttention($items, $counts['pendingApprovals'] ?? 0, 'Packages await your review', route('dashboard.packages', ['status' => 'awaiting_review']), 'danger');
            $this->pushAttention($items, $counts['packagesNeedingRework'] ?? 0, 'Packages need rework', route('dashboard.packages', ['status' => 'needs_rework']), 'warning');
            $this->pushAttention($items, $counts['projectsNeedingSupervisor'] ?? 0, 'Your projects need a supervisor', route('projects.index', ['assignment' => 'needs_supervisor']), 'warning');
            $this->pushAttention($items, $counts['projectsStalled'] ?? 0, 'Your projects stalled for '.Project::STALLED_ACTIVITY_DAYS.' days', route('projects.index', ['assignment' => 'stalled']), 'warning');
            $this->pushAttention($items, $counts['changeOrdersPendingCustomer'] ?? 0, 'Change orders awaiting customer decision', route('change-orders.index', ['status' => 'customer_action_required']), 'warning');
            $this->pushAttention($items, $counts['packagesReadyToSubmit'] ?? 0, 'Packages ready to submit', route('dashboard.packages', ['status' => 'ready_to_submit']), 'info');
        } elseif ($user->hasRole(Role::FIELD_SUPERVISOR->value)) {
            $this->pushAttention($items, $counts['packagesNeedingRework'] ?? 0, 'Packages need your attention', route('dashboard.packages', ['status' => 'needs_rework']), 'danger');
            $this->pushAttention($items, $counts['packagesReadyToSubmit'] ?? 0, 'Packages ready to submit', route('dashboard.packages', ['status' => 'ready_to_submit']), 'info');
        } else {
            // Customer — informational status, nothing to act on.
            $this->pushAttention($items, $counts['packagesNeedingRework'] ?? 0, 'Packages need rework', route('dashboard.packages', ['status' => 'needs_rework']), 'warning');
            $this->pushAttention($items, $counts['pendingApprovals'] ?? 0, 'Packages awaiting final approval', route('dashboard.packages', ['status' => 'awaiting_review']), 'info');
            $this->pushAttention($items, $counts['packagesApproved'] ?? 0, 'Packages fully approved', route('dashboard.packages', ['status' => 'approved']), 'success');
        }

        usort($items, fn (array $a, array $b): int => $this->toneRank($a['tone']) <=> $this->toneRank($b['tone']));

        return $items;
    }

    /**
     * @param  list<array{key: string, label: string, count: int, href: string, tone: string}>  $items
     */
    private function pushAttention(array &$items, int $count, string $label, string $href, string $tone): void
    {
        if ($count <= 0) {
            return;
        }

        $items[] = [
            'key' => (string) Str::slug($label),
            'label' => $label,
            'count' => $count,
            'href' => $href,
            'tone' => $tone,
        ];
    }

    private function toneRank(string $tone): int
    {
        return match ($tone) {
            'danger' => 0,
            'warning' => 1,
            'info' => 2,
            'success' => 3,
            default => 4,
        };
    }
}
