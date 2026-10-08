<?php

declare(strict_types=1);

namespace App\Domains\Customers\Queries;

use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerEngineer;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Read models for the customer hierarchy: the App Settings console and the
 * project form's cascading Customer → Region (market) → Engineer selector.
 */
final class CustomerHierarchyQuery
{
    /**
     * @return Collection<int, Customer>
     */
    public function activeCustomers(): Collection
    {
        return Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    /**
     * Regions are markets — one geographic list for the whole application.
     *
     * @return Collection<int, Market>
     */
    public function activeMarkets(): Collection
    {
        return Market::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Customers with their engineers (and each engineer's region + assigned
     * projects) eager-loaded — the workload list customers and managers see.
     *
     * @param  list<int>|null  $marketIds  Restrict engineers to these regions; null = all.
     * @return Collection<int, Customer>
     */
    public function customersWithEngineers(?array $marketIds = null): Collection
    {
        return Customer::query()
            ->withCount('projects')
            ->with(['customerEngineers' => function (Relation $query) use ($marketIds): void {
                if ($marketIds !== null) {
                    $query->whereIn('market_id', $marketIds);
                }

                $query
                    ->with([
                        'market:id,name',
                        'user:id',
                        'user.media' => fn (Relation $media) => $media
                            ->where('collection_name', User::PROFILE_PHOTO_COLLECTION),
                        'projects' => fn (Relation $projects) => $projects
                            ->whereNotIn('status', [ProjectStatus::COMPLETED->value, ProjectStatus::ARCHIVED->value])
                            ->orderByDesc('created_at')
                            ->select(['id', 'customer_engineer_id', 'name', 'status']),
                    ])
                    ->orderByDesc('is_default')
                    ->orderBy('name');
            }])
            ->orderBy('name')
            ->get();
    }

    /**
     * The regions a viewer may see, mirroring CustomerEngineerPolicy's write
     * scope. Null means "every region" (admins, and callers with no viewer).
     *
     * @return list<int>|null
     */
    private function viewableMarketIds(?User $viewer): ?array
    {
        if ($viewer === null || $viewer->hasRole(Role::ADMIN->value)) {
            return null;
        }

        return $viewer->markets()->pluck('markets.id')->all();
    }

    /**
     * Selectable engineers for one customer + region, default first.
     *
     * @return Collection<int, CustomerEngineer>
     */
    public function engineersFor(int $customerId, int $marketId): Collection
    {
        return CustomerEngineer::query()
            ->where('customer_id', $customerId)
            ->where('market_id', $marketId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'customer_id', 'market_id', 'name', 'email', 'phone', 'title', 'is_default']);
    }

    /**
     * Selectable engineers keyed by customer id, then region (market) id — the
     * shape the project form's cascading selector consumes. Defaults come first.
     *
     * @return array<int, array<int, list<array{id: int, name: string, email: string, phone: string|null, title: string|null, is_default: bool}>>>
     */
    public function engineersByCustomerAndMarket(): array
    {
        $grouped = [];

        $engineers = CustomerEngineer::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'customer_id', 'market_id', 'name', 'email', 'phone', 'title', 'is_default']);

        foreach ($engineers as $engineer) {
            $grouped[$engineer->customer_id][$engineer->market_id][] = [
                'id' => $engineer->id,
                'name' => $engineer->name,
                'email' => $engineer->email,
                'phone' => $engineer->phone,
                'title' => $engineer->title,
                'is_default' => $engineer->is_default,
            ];
        }

        return $grouped;
    }

    /**
     * The pre-selected engineer when creating a project for a customer + region.
     * Selects the designated default engineer, or falls back to the first active engineer for this territory.
     */
    public function defaultEngineerFor(int $customerId, int $marketId): ?CustomerEngineer
    {
        return CustomerEngineer::query()
            ->where('customer_id', $customerId)
            ->where('market_id', $marketId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Payload for the App Settings "Customer Account Settings" console.
     *
     * A manager only oversees the regions assigned to them, so the console is
     * narrowed to match: their engineers only, and only those regions in the
     * region picker. Customer names stay unscoped because the picker needs the
     * company an engineer is being added under. Null viewer = admin, unfiltered.
     *
     * @return array{
     *     customers: array<int, array{id: int, hash_id: string, name: string, code: string, description: string|null, is_active: bool, projects_count: int, engineers: array<int, array{id: int, hash_id: string, customer_id: int, market_id: int, market_name: string|null, name: string, email: string, phone: string|null, title: string|null, is_default: bool, is_active: bool, has_portal_account: bool, profile_photo_url: string|null, assigned_projects: array<int, array{id: int, name: string, status: string|null}>}>}>,
     *     regions: array<int, array{id: int, name: string}>,
     *     metrics: array{customers: int, regions: int, active_engineers: int}
     * }
     */
    public function settingsPayload(?User $viewer = null): array
    {
        $marketIds = $this->viewableMarketIds($viewer);
        $customers = $this->customersWithEngineers($marketIds);
        $regions = $marketIds === null
            ? $this->activeMarkets()
            : Market::query()->active()->whereIn('id', $marketIds)->orderBy('name')->get(['id', 'name']);

        return [
            'customers' => $customers
                ->map(fn (Customer $customer): array => [
                    'id' => $customer->id,
                    'hash_id' => $customer->getRouteKey(),
                    'name' => $customer->name,
                    'code' => $customer->code,
                    'description' => $customer->description,
                    'is_active' => $customer->is_active,
                    'projects_count' => $customer->projects_count,
                    'engineers' => $customer->customerEngineers
                        ->map(fn (CustomerEngineer $engineer): array => [
                            'id' => $engineer->id,
                            'hash_id' => $engineer->getRouteKey(),
                            'customer_id' => $engineer->customer_id,
                            'market_id' => $engineer->market_id,
                            'market_name' => $engineer->market?->name,
                            'name' => $engineer->name,
                            'email' => $engineer->email,
                            'phone' => $engineer->phone,
                            'title' => $engineer->title,
                            'is_default' => $engineer->is_default,
                            'is_active' => $engineer->is_active,
                            'has_portal_account' => $engineer->user_id !== null,
                            'profile_photo_url' => $engineer->user?->profilePhotoUrl(),
                            'assigned_projects' => $engineer->projects
                                ->map(fn (Project $project): array => [
                                    'id' => $project->id,
                                    'name' => $project->name,
                                    'status' => $project->status->value,
                                ])
                                ->values()
                                ->all(),
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'regions' => $regions
                ->map(fn (Market $market): array => [
                    'id' => $market->id,
                    'name' => $market->name,
                ])
                ->values()
                ->all(),
            'metrics' => [
                'customers' => $customers->count(),
                'regions' => $regions->count(),
                'active_engineers' => $customers->sum(
                    fn (Customer $customer): int => $customer->customerEngineers->where('is_active', true)->count(),
                ),
            ],
        ];
    }
}
