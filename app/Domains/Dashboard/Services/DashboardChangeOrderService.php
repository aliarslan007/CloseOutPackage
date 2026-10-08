<?php

declare(strict_types=1);

namespace App\Domains\Dashboard\Services;

use App\Domains\ChangeOrders\Enums\ChangeOrderStatus;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Database\Eloquent\Builder;

final class DashboardChangeOrderService
{
    /**
     * @param  list<int>  $projectIds
     * @return array{
     *     showChangeOrders: bool,
     *     totalChangeOrders: int,
     *     changeOrdersDraft: int,
     *     changeOrdersPendingCustomer: int,
     *     changeOrdersApproved: int,
     *     changeOrdersRejected: int,
     *     changeOrdersTotalValue: float,
     *     changeOrdersApprovedValue: float,
     *     recentChangeOrders: list<array{
     *         id: int,
     *         hash_id: string,
     *         title: string,
     *         project_name: string,
     *         status: string,
     *         status_label: string,
     *         total_amount: string,
     *         created_at: string|null,
     *         href: string
     *     }>
     * }
     */
    public function changeOrderStats(User $user, array $projectIds): array
    {
        $showChangeOrders = $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]);

        if (! $showChangeOrders) {
            return [
                'showChangeOrders' => false,
                'totalChangeOrders' => 0,
                'changeOrdersDraft' => 0,
                'changeOrdersPendingCustomer' => 0,
                'changeOrdersApproved' => 0,
                'changeOrdersRejected' => 0,
                'changeOrdersTotalValue' => 0.0,
                'changeOrdersApprovedValue' => 0.0,
                'recentChangeOrders' => [],
            ];
        }

        $changeOrdersQuery = $this->scopedChangeOrders($user, $projectIds);
        $changeOrderStats = (clone $changeOrdersQuery)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as draft,
                SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as pending_customer,
                SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected,
                SUM(total_amount) as total_value,
                SUM(CASE WHEN status IN (?, ?) THEN total_amount ELSE 0 END) as approved_value
            ', [
                ChangeOrderStatus::DRAFT->value,
                ChangeOrderStatus::SENT_TO_CUSTOMER->value,
                ChangeOrderStatus::CUSTOMER_ACTION_REQUIRED->value,
                ChangeOrderStatus::APPROVED->value,
                ChangeOrderStatus::PARTIALLY_APPROVED->value,
                ChangeOrderStatus::REJECTED->value,
                ChangeOrderStatus::APPROVED->value,
                ChangeOrderStatus::PARTIALLY_APPROVED->value,
            ])
            ->first();

        /** @var list<array{id: int, hash_id: string, title: string, project_name: string, status: string, status_label: string, total_amount: string, created_at: string|null, href: string}> */
        $recentChangeOrders = (clone $changeOrdersQuery)
            ->with('project:id,name')
            ->latest()
            ->limit(3)
            ->get()
            ->map(function (ChangeOrder $co): array {
                return [
                    'id' => $co->id,
                    'hash_id' => $co->hash_id,
                    'title' => $co->title,
                    'project_name' => $co->project?->name ?? 'Unknown Project',
                    'status' => $co->status->value,
                    'status_label' => $co->status->label(),
                    'total_amount' => number_format((float) $co->total_amount, 2),
                    'created_at' => $co->created_at?->toIso8601String(),
                    'href' => $co->project !== null
                        ? route('projects.change-orders.show', [$co->project->hash_id, $co->hash_id])
                        : route('change-orders.index'),
                ];
            })
            ->all();

        return [
            'showChangeOrders' => true,
            'totalChangeOrders' => (int) ($changeOrderStats?->total ?? 0),
            'changeOrdersDraft' => (int) ($changeOrderStats?->draft ?? 0),
            'changeOrdersPendingCustomer' => (int) ($changeOrderStats?->pending_customer ?? 0),
            'changeOrdersApproved' => (int) ($changeOrderStats?->approved ?? 0),
            'changeOrdersRejected' => (int) ($changeOrderStats?->rejected ?? 0),
            'changeOrdersTotalValue' => (float) ($changeOrderStats?->total_value ?? 0),
            'changeOrdersApprovedValue' => (float) ($changeOrderStats?->approved_value ?? 0),
            'recentChangeOrders' => $recentChangeOrders,
        ];
    }

    /**
     * @param  list<int>  $projectIds
     * @return Builder<ChangeOrder>
     */
    public function scopedChangeOrders(User $user, array $projectIds): Builder
    {
        $query = ChangeOrder::query()->whereHas('project');

        return match (true) {
            $user->hasRole(Role::ADMIN->value) => $query,
            $user->hasRole(Role::MANAGER->value) => $query->where(function (Builder $b) use ($user, $projectIds): void {
                $b->where('created_by', $user->id);

                if ($projectIds !== []) {
                    $b->orWhereIn('project_id', $projectIds);
                }
            }),
            $projectIds === [] => $query->whereRaw('1 = 0'),
            $user->hasRole(Role::CUSTOMER->value) => $query
                ->whereIn('project_id', $projectIds)
                ->where('status', '!=', ChangeOrderStatus::DRAFT->value),
            default => $query->whereIn('project_id', $projectIds),
        };
    }
}
