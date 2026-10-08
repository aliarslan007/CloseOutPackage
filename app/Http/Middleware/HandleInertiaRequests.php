<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Middleware;
use Spatie\Permission\Models\Permission;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $permissions = $user instanceof User ? $user->getAllPermissions() : collect();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user instanceof User ? $this->userPayload($user, $permissions) : null,
                'markets' => $user instanceof User ? $this->markets($user) : [],
                'can' => $user instanceof User ? $this->permissionMap($permissions) : [],
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
            ],
            // One message length for every review surface (change order, package,
            // issue, emailed token), so the counter the client renders is the rule
            // the server enforces.
            'feedbackMessageLimit' => EntityFeedbackNote::MAX_BODY_LENGTH,
            'feedbackEditWindowMinutes' => EntityFeedbackNote::editWindowMinutes(),
            'feedbackBadge' => $user instanceof User ? $this->feedbackBadge($user) : 0,
            'pendingFeedbackRound' => $user instanceof User ? $this->pendingFeedbackRound($user) : null,
            'app' => [
                'version' => config('app.version', '1.0.0'),
            ],
        ];
    }

    /**
     * Number of customer feedback rounds awaiting manager action
     * (plan §17: navigation badge for admins/managers).
     */
    private function feedbackBadge(User $user): int
    {
        $isAdmin = $user->hasRole(Role::ADMIN->value);

        if (! $isAdmin && ! $user->hasRole(Role::MANAGER->value)) {
            return 0;
        }

        return $this->scopedRoundsFor($user)->count();
    }

    /**
     * Rounds whose entity belongs to a project the user manages, across every
     * entity type. Without this the badge would leak another manager's projects.
     *
     * @return Builder<EntityFeedbackRound>
     */
    private function scopedRoundsFor(User $user)
    {
        $projectIds = Project::query()
            ->where('manager_id', $user->id)
            ->pluck('id');

        $byEntity = fn (FeedbackEntityType $type, string $model, string $projectColumn): array => [
            'type' => $type,
            'query' => $model::query()->whereIn($projectColumn, $projectIds)->select('id'),
        ];

        return EntityFeedbackRound::query()
            ->where(function (Builder $query) use ($byEntity): void {
                foreach ([
                    $byEntity(FeedbackEntityType::CHANGE_ORDER, ChangeOrder::class, 'project_id'),
                    $byEntity(FeedbackEntityType::ISSUE, IssueHistory::class, 'project_id'),
                    $byEntity(FeedbackEntityType::CLOSEOUT_PACKAGE, CloseoutPackage::class, 'project_id'),
                ] as $branch) {
                    $query->orWhere(function (Builder $inner) use ($branch): void {
                        $inner->where('entity_type', $branch['type']->value)
                            ->whereIn('entity_id', $branch['query']);
                    });
                }
            });
    }

    /**
     * Lightweight summary of the customer's active feedback round
     * (plan §17: powers the portal layout banner).
     *
     * @return array{package_hash_id: string, round_number: int, status: string}|null
     */
    private function pendingFeedbackRound(User $user): ?array
    {
        if (! $user->hasRole(Role::CUSTOMER->value)) {
            return null;
        }

        $projectIds = CustomerProjectAccess::query()
            ->where('user_id', $user->id)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->pluck('project_id');

        if ($projectIds->isEmpty()) {
            return null;
        }

        $round = EntityFeedbackRound::query()
            ->where('entity_type', FeedbackEntityType::CLOSEOUT_PACKAGE->value)
            ->whereIn('entity_id', CloseoutPackage::query()
                ->whereIn('project_id', $projectIds)
                ->select('id'))
            ->whereIn('status', [
                FeedbackRoundStatus::OPEN->value,
                FeedbackRoundStatus::SUBMITTED->value,
                FeedbackRoundStatus::RESOLVED->value,
            ])
            ->orderByDesc('id')
            ->first();

        $package = $round === null
            ? null
            : CloseoutPackage::query()->find($round->entity_id);

        if ($package === null) {
            return null;
        }

        return [
            'package_hash_id' => $package->hash_id,
            'round_number' => $round->round_number,
            'status' => $round->status->value,
        ];
    }

    /**
     * @param  Collection<int, Permission>  $permissions
     * @return array<string, mixed>
     */
    private function userPayload(User $user, Collection $permissions): array
    {
        // Load the media relation explicitly so profilePhotoUrl() never triggers
        // a lazy load (preventLazyLoading is enabled outside production).
        $user->loadMissing('media');

        /** @var array<string, mixed> $payload */
        $payload = $user->toArray();

        return array_merge($payload, [
            'roles' => $user->getRoleNames()->all(),
            'permissions' => $permissions->pluck('name')->all(),
            'profile_photo_url' => $user->profilePhotoUrl(),
        ]);
    }

    /**
     * Selectable markets, shared globally via props.markets.
     *
     * Scoped to active so an inactive region can never appear in any dropdown
     * that reads this prop. Admin market management does not use this prop — it
     * lists through MarketQuery::listForAdmin(), which keeps deactivated markets
     * visible so they can be reactivated.
     *
     * @return list<array{id: int, name: string}>
     */
    private function markets(User $user): array
    {
        $query = Market::query()->active()->orderBy('name');

        if (! $user->hasRole(Role::ADMIN->value)) {
            $query->whereIn('id', $user->markets()->select('markets.id'));
        }

        return $query->get(['id', 'name'])
            ->map(fn (Market $market): array => ['id' => $market->id, 'name' => $market->name])
            ->all();
    }

    /**
     * @param  Collection<int, Permission>  $permissions
     * @return array<string, bool>
     */
    private function permissionMap(Collection $permissions): array
    {
        return $permissions->pluck('name')
            ->mapWithKeys(fn (string $name): array => [$name => true])
            ->all();
    }
}
