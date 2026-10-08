<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Queries;

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderRecipient;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\CustomerPortal\Services\CustomerPortalScopeService;
use App\Domains\Customers\Models\CustomerEngineer;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Everything the all-projects change order page renders: the role-scoped order
 * list, the matching project list, and the customer options the page can filter
 * by. Extracted from the controller so the role rules sit next to each other
 * instead of being spread through a request handler.
 */
final class ChangeOrderIndexQuery
{
    public function __construct(
        private readonly CustomerPortalScopeService $scopeService,
    ) {}

    /**
     * @return array{changeOrders: Collection<int, ChangeOrder>, projects: Collection<int, Project>, customers: array<int, array{id: int, name: string, email: string, company: string|null, profile_photo_url: string|null}>}
     */
    public function build(User $user): array
    {
        $changeOrders = $this->ordersQuery()
            ->tap(fn (Builder $query) => $this->applyRoleScope($query, $user))
            ->get()
            ->filter(fn (ChangeOrder $co): bool => $co->project !== null)
            ->values();

        self::withProfilePhotos($changeOrders);

        $projects = $this->projectsQuery($user)
            ->select(['id', 'name', 'project_number', 'ps_number', 'address', 'city', 'state', 'zip', 'customer_id'])
            ->orderBy('name')
            ->get();

        return [
            'changeOrders' => $changeOrders,
            'projects' => $projects,
            'customers' => $this->customerOptions($changeOrders, $projects, $user),
        ];
    }

    /**
     * @return Builder<ChangeOrder>
     */
    private function ordersQuery(): Builder
    {
        return ChangeOrder::query()
            ->whereHas('project')
            ->with(['project.market', 'project.customer', 'creator.media', 'items', 'recipients.user.media'])
            ->withCount('recipients')
            // The all-projects list has its own sequence, independent of the
            // per-project `sort_order` used by the project page.
            ->orderBy('global_sort_order')
            ->orderByDesc('id');
    }

    /**
     * Narrow the order list to what the viewer is allowed to see.
     *
     * @param  Builder<ChangeOrder>  $query
     */
    private function applyRoleScope(Builder $query, User $user): void
    {
        if ($user->hasRole('customer')) {
            $accessibleProjectIds = $this->scopeService->accessibleProjects($user)->pluck('projects.id');
            // Drafts are internal until they are sent.
            $query->whereIn('project_id', $accessibleProjectIds)
                ->where('status', '!=', 'draft');

            return;
        }

        if ($user->hasRole('admin')) {
            return;
        }

        // Managers only see the projects assigned to them, not every project
        // in their markets. Mirrors ProjectQuery::forIndex and the dashboard,
        // and keeps the create-change-order project picker honest.
        $query->whereHas('project', fn (Builder $q) => $q->where('manager_id', $user->id));
    }

    /**
     * The project list is scoped the same way as the orders, so the filter can
     * never offer a project whose orders are all hidden.
     *
     * @return Builder<Project>
     */
    private function projectsQuery(User $user): Builder
    {
        if ($user->hasRole('customer')) {
            return Project::query()
                ->whereIn('id', $this->scopeService->accessibleProjects($user)->select('projects.id'));
        }

        if ($user->hasRole('admin')) {
            return Project::query();
        }

        return Project::query()->where('manager_id', $user->id);
    }

    /**
     * The order list and the badges read `profile_photo_url` off the user, which
     * is an accessor over the media library rather than a column.
     *
     * @param  Collection<int, ChangeOrder>  $changeOrders
     */
    private static function withProfilePhotos(Collection $changeOrders): void
    {
        $changeOrders->each(function (ChangeOrder $co): void {
            if ($co->creator !== null) {
                $co->creator->setAttribute('profile_photo_url', $co->creator->profilePhotoUrl());
            }

            $co->recipients->each(function (ChangeOrderRecipient $recipient): void {
                if ($recipient->user !== null) {
                    $recipient->user->setAttribute('profile_photo_url', $recipient->user->profilePhotoUrl());
                }
            });
        });
    }

    /**
     * The contacts the page can filter by, gathered from everyone reachable
     * through the viewer's own orders and project grants.
     *
     * @param  Collection<int, ChangeOrder>  $changeOrders
     * @param  Collection<int, Project>  $projects
     * @return array<int, array{id: int, name: string, email: string, company: string|null, profile_photo_url: string|null}>
     */
    private function customerOptions(Collection $changeOrders, Collection $projects, User $user): array
    {
        // A customer is their own scope. The dropdown is hidden for them, and
        // the gathering below walks their peers' change orders and project
        // grants — handing it straight to the page would expose other
        // accounts' contact names, emails and companies.
        if ($user->hasRole('customer')) {
            return [$this->presentCustomer($user)];
        }

        $accessibleProjectIds = $projects->pluck('id');

        $users = $changeOrders
            ->flatMap(fn (ChangeOrder $co) => $co->recipients)
            ->map(fn (ChangeOrderRecipient $r) => $r->user)
            ->filter()
            ->concat(
                CustomerProjectAccess::query()
                    ->whereIn('project_id', $accessibleProjectIds)
                    ->with(['user.media'])
                    ->get()
                    ->map(fn (CustomerProjectAccess $access) => $access->user)
                    ->filter()
            )
            ->concat($this->engineerUsers($accessibleProjectIds))
            // Only an admin sees the whole system's contacts; a manager's list
            // stays bounded by what their own orders already reached.
            ->concat(
                $user->hasRole('admin')
                    ? User::role('customer')->with(['media'])->get()
                    : new Collection
            )
            ->unique('id')
            ->values();

        return $users
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (User $u): array => $this->presentCustomer($u))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, mixed>  $accessibleProjectIds
     * @return Collection<int, User>
     */
    private function engineerUsers(Collection $accessibleProjectIds): Collection
    {
        return CustomerEngineer::query()
            ->whereNotNull('user_id')
            ->where(function (Builder $query) use ($accessibleProjectIds): void {
                $query->whereHas('customer.projects', fn (Builder $sub) => $sub->whereIn('id', $accessibleProjectIds))
                    ->orWhereHas('user.customerAccesses', fn (Builder $sub) => $sub->whereIn('project_id', $accessibleProjectIds));
            })
            ->with(['user.media', 'customer'])
            ->get()
            ->map(function (CustomerEngineer $engineer): ?User {
                $engineerUser = $engineer->user;

                if ($engineerUser === null) {
                    return null;
                }

                if (empty($engineerUser->company) && $engineer->customer !== null) {
                    $engineerUser->company = $engineer->customer->name;
                }

                return $engineerUser;
            })
            ->filter();
    }

    /**
     * @return array{id: int, name: string, email: string, company: string|null, profile_photo_url: string|null}
     */
    private function presentCustomer(User $user): array
    {
        $company = $user->company;

        if (empty($company)) {
            $engineer = CustomerEngineer::where('user_id', $user->id)->with('customer')->first();
            $company = $engineer?->customer?->name;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'company' => $company ?: null,
            'profile_photo_url' => $user->profilePhotoUrl(),
        ];
    }
}
