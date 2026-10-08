<?php

declare(strict_types=1);

namespace App\Domains\Dashboard\Services;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\CloseoutPackages\Queries\PackageStatusFilter;
use App\Domains\CloseoutPackages\Support\PackageLockFlags;
use App\Domains\CustomerPortal\Services\CustomerPortalScopeService;
use App\Domains\Dashboard\DTOs\DashboardStatsDTO;
use App\Domains\Email\Enums\EmailStatus;
use App\Domains\Email\Models\EmailLog;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

final class DashboardQueryService
{
    private const CACHE_TTL_SECONDS = 120;

    private const ACTIVITY_PER_PAGE = 8;

    private const PACKAGES_PER_PAGE = 15;

    /** Statuses that are no longer "active" work and drop out of the lifecycle buckets. */
    private const INACTIVE_PROJECT_STATUSES = [
        ProjectStatus::COMPLETED->value,
        ProjectStatus::ARCHIVED->value,
    ];

    public function __construct(
        private readonly CustomerPortalScopeService $customerScope,
        private readonly PackageStatusFilter $packageStatusFilter,
        private readonly CustomerDashboardQueryService $customerQueryService,
        private readonly DashboardAttentionService $attentionService,
        private readonly DashboardChangeOrderService $changeOrderService,
    ) {}

    public function statsFor(User $user): DashboardStatsDTO
    {
        return $this->remember($user, fn (): DashboardStatsDTO => $this->build($user));
    }

    /**
     * @return LengthAwarePaginator<int, array{
     *     id: int,
     *     log_name: string|null,
     *     description: string,
     *     subject_type: string,
     *     subject_id: int|null,
     *     causer_name: string,
     *     causer_email: string|null,
     *     properties: array<mixed>,
     *     created_at: string|null,
     * }>
     */
    public function activityLogsFor(User $user, bool $showAll = false): LengthAwarePaginator
    {
        $query = Activity::query()->with(['causer', 'subject'])->latest();

        // Managers only see activity caused by themselves or the field
        // supervisors assigned to their projects. Admins see everything.
        if (! $user->hasRole(Role::ADMIN->value)) {
            $supervisorIds = $this->supervisorIdsForManager($user);

            $query->where(function (Builder $builder) use ($user, $supervisorIds): void {
                $builder->where('causer_id', $user->id)
                    ->orWhereIn('causer_id', $supervisorIds);
            });
        }

        $paginator = $showAll
            ? $query->paginate(max(1, $query->count()))
            : $query->paginate(self::ACTIVITY_PER_PAGE);

        return $paginator->through(function (Activity $act): array {
            /** @var User|null $causer */
            $causer = $act->causer;

            return [
                'id' => $act->id,
                'log_name' => $act->log_name,
                'description' => $act->description,
                'subject_type' => class_basename($act->subject_type ?? ''),
                'subject_id' => $act->subject_id,
                'causer_name' => $causer !== null ? $causer->name : 'System',
                'causer_email' => $causer !== null ? $causer->email : null,
                'properties' => $act->properties?->toArray() ?? [],
                'created_at' => $act->created_at?->toIso8601String(),
            ];
        });
    }

    /**
     * Paginated, status-filterable package list scoped to the user.
     * Supports the dashboard funnel buckets plus any raw package status.
     *
     * @return LengthAwarePaginator<int, CloseoutPackage>
     */
    public function packagesFor(User $user, ?string $status): LengthAwarePaginator
    {
        $scoped = CloseoutPackage::query()
            ->whereIn('project_id', (clone $this->scopedProjects($user))->select('id'));

        if ($user->hasRole(Role::CUSTOMER->value)) {
            $scoped->whereIn('status', CloseoutPackage::CUSTOMER_ACCESSIBLE_STATUSES)
                ->whereNull('locked_at');
        }

        $query = (clone $scoped)
            ->with(['project:id,name,manager_id', 'template:id,name'])
            ->withPendingFeedbackCount();

        $this->packageStatusFilter->apply($query, $scoped, $status);

        $packages = $query->latest()->paginate(self::PACKAGES_PER_PAGE);

        PackageLockFlags::attach($packages->items(), $user);

        return $packages;
    }

    private function build(User $user): DashboardStatsDTO
    {
        $base = $this->scopedProjects($user);
        /** @var list<int> $projectIds */
        $projectIds = (clone $base)->pluck('id')->all();

        $lifecycle = $this->projectLifecycleStats($base);

        $isCustomer = $user->hasRole(Role::CUSTOMER->value);

        $packages = CloseoutPackage::query()->whereIn('project_id', $projectIds);
        $pipeline = $this->packagePipelineStats($base, $packages, $projectIds);

        $media = $this->photoAndIssueStats($packages, $projectIds, $pipeline['packageIds'], $isCustomer);

        $changeOrders = $this->changeOrderService->changeOrderStats($user, $projectIds);

        [$emailsSentToday, $emailsFailedToday] = $this->emailsToday($user, $projectIds);

        $attentionItems = $this->attentionService->attentionItems($user, [
            'projectsNeedingManager' => $lifecycle['projectsNeedingManager'],
            'projectsNeedingSupervisor' => $lifecycle['projectsNeedingSupervisor'],
            'projectsStalled' => $lifecycle['projectsStalled'],
            'pendingApprovals' => $pipeline['pendingApprovals'],
            'packagesApproved' => $pipeline['packagesApproved'],
            'packagesNeedingRework' => $pipeline['packagesNeedingRework'],
            'packagesReadyToSubmit' => $pipeline['packagesReadyToSubmit'],
            'changeOrdersPendingCustomer' => $changeOrders['changeOrdersPendingCustomer'],
        ]);

        $isCustomer = $user->hasRole(Role::CUSTOMER->value);

        return new DashboardStatsDTO(
            totalProjects: $lifecycle['totalProjects'],
            projectsPendingSubmission: $pipeline['projectsPendingSubmission'],
            completedProjects: $lifecycle['completedProjects'],
            pendingApprovals: $pipeline['pendingApprovals'],
            flaggedPhotos: $media['flaggedPhotos'],
            photosAwaitingReview: $media['photosAwaitingReview'],
            openIssues: $media['openIssues'],
            avgProgress: $lifecycle['avgProgress'],
            recentSubmissions: $media['recentSubmissions'],
            marketHealth: $this->marketHealth($base),
            projectsNeedingManager: $lifecycle['projectsNeedingManager'],
            projectsNeedingSupervisor: $lifecycle['projectsNeedingSupervisor'],
            projectsNotStarted: $lifecycle['projectsNotStarted'],
            projectsInProgress: $lifecycle['projectsInProgress'],
            projectsStalled: $lifecycle['projectsStalled'],
            totalPackages: $pipeline['totalPackages'],
            packagesApproved: $pipeline['packagesApproved'],
            packagesReadyToSubmit: $pipeline['packagesReadyToSubmit'],
            packagesNeedingRework: $pipeline['packagesNeedingRework'],
            packagesRejected: $pipeline['packagesRejected'],
            packagesWithFlaggedPhotos: $pipeline['packagesWithFlaggedPhotos'],
            packagesDraft: $pipeline['packagesDraft'],
            photosCaptured: $media['photosCaptured'],
            photosUploaded: $media['photosUploaded'],
            photosRequired: $media['photosRequired'],
            emailsSentToday: $emailsSentToday,
            emailsFailedToday: $emailsFailedToday,
            momentum: $this->momentum($projectIds),
            attentionItems: $attentionItems,
            customerActivity: $isCustomer ? $this->customerQueryService->customerActivity($base) : [],
            customerProjects: $isCustomer ? $this->customerQueryService->customerProjects($base) : [],
            customerAttention: $isCustomer ? $this->customerQueryService->customerAttention($base) : [],
            recentPhotos: $isCustomer ? $this->customerQueryService->recentPhotos($packages) : [],
            fieldActivity: $isCustomer ? $this->customerQueryService->fieldActivity($base) : [
                'technicians' => 0,
                'project' => null,
                'href' => null,
                'updated_at' => null,
            ],
            totalChangeOrders: $changeOrders['totalChangeOrders'],
            changeOrdersDraft: $changeOrders['changeOrdersDraft'],
            changeOrdersPendingCustomer: $changeOrders['changeOrdersPendingCustomer'],
            changeOrdersApproved: $changeOrders['changeOrdersApproved'],
            changeOrdersRejected: $changeOrders['changeOrdersRejected'],
            changeOrdersTotalValue: $changeOrders['changeOrdersTotalValue'],
            changeOrdersApprovedValue: $changeOrders['changeOrdersApprovedValue'],
            recentChangeOrders: $changeOrders['recentChangeOrders'],
            sectionVisible: [
                'changeOrders' => $changeOrders['showChangeOrders'],
            ],
        );
    }

    /**
     * @param  Builder<Project>  $base
     * @return array{
     *     totalProjects: int,
     *     completedProjects: int,
     *     avgProgress: int,
     *     projectsNeedingManager: int,
     *     projectsNeedingSupervisor: int,
     *     projectsNotStarted: int,
     *     projectsInProgress: int,
     *     projectsStalled: int,
     * }
     */
    private function projectLifecycleStats(Builder $base): array
    {
        $projectSummary = (clone $base)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed,
                AVG(progress_percentage) as avg_progress
            ', [ProjectStatus::COMPLETED->value])
            ->first();

        $projectsNeedingManager = (clone $base)
            ->whereNull('manager_id')
            ->whereNotIn('status', self::INACTIVE_PROJECT_STATUSES)
            ->count();
        $projectsNeedingSupervisor = (clone $base)
            ->whereNotNull('manager_id')
            ->whereDoesntHave('fieldSupervisors')
            ->whereNotIn('status', self::INACTIVE_PROJECT_STATUSES)
            ->count();
        $projectsNotStarted = $this->staffedProjects(clone $base)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('progress_percentage')
                ->orWhere('progress_percentage', 0))
            ->count();
        $projectsInProgress = $this->staffedProjects(clone $base)
            ->whereBetween('progress_percentage', [1, 99])
            ->count();
        $projectsStalled = $this->stalledProjects($base);

        return [
            'totalProjects' => (int) ($projectSummary?->total ?? 0),
            'completedProjects' => (int) ($projectSummary?->completed ?? 0),
            'avgProgress' => (int) round((float) ($projectSummary?->avg_progress ?? 0)),
            'projectsNeedingManager' => $projectsNeedingManager,
            'projectsNeedingSupervisor' => $projectsNeedingSupervisor,
            'projectsNotStarted' => $projectsNotStarted,
            'projectsInProgress' => $projectsInProgress,
            'projectsStalled' => $projectsStalled,
        ];
    }

    /**
     * @param  Builder<Project>  $base
     * @param  Builder<CloseoutPackage>  $packages
     * @param  list<int>  $projectIds
     * @return array{
     *     totalPackages: int,
     *     pendingApprovals: int,
     *     packagesApproved: int,
     *     packagesRejected: int,
     *     packagesDraft: int,
     *     projectsPendingSubmission: int,
     *     packageIds: list<int>,
     *     packagesWithFlaggedPhotos: int,
     *     packagesNeedingRework: int,
     *     packagesReadyToSubmit: int,
     * }
     */
    private function packagePipelineStats(Builder $base, Builder $packages, array $projectIds): array
    {
        $packageStats = $projectIds !== [] ? (clone $packages)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as draft
            ', [
                PackageStatus::SUBMITTED->value,
                PackageStatus::UNDER_REVIEW->value,
                PackageStatus::APPROVED->value,
                PackageStatus::REJECTED->value,
                PackageStatus::DRAFT->value,
            ])
            ->first() : null;

        $projectsPendingSubmission = (clone $base)
            ->whereHas('packages', function (Builder $query): void {
                $query->whereIn('status', [PackageStatus::SUBMITTED->value, PackageStatus::UNDER_REVIEW->value]);
            })
            ->count();

        /** @var list<int> $packageIds */
        $packageIds = $projectIds !== [] ? (clone $packages)->pluck('id')->all() : [];

        $flaggedPackageIds = $this->packageStatusFilter->flaggedPackageIds($packages, $packageIds);
        $packagesWithFlaggedPhotos = count($flaggedPackageIds);
        $packagesNeedingRework = $projectIds !== [] ? (clone $packages)
            ->where(function (Builder $query) use ($flaggedPackageIds): void {
                $query->where('status', PackageStatus::REJECTED->value);

                if ($flaggedPackageIds !== []) {
                    $query->orWhereIn('id', $flaggedPackageIds);
                }
            })
            ->count() : 0;
        $packagesReadyToSubmit = count($this->packageStatusFilter->readyToSubmitIds($packages));

        return [
            'totalPackages' => (int) ($packageStats?->total ?? 0),
            'pendingApprovals' => (int) ($packageStats?->pending ?? 0),
            'packagesApproved' => (int) ($packageStats?->approved ?? 0),
            'packagesRejected' => (int) ($packageStats?->rejected ?? 0),
            'packagesDraft' => (int) ($packageStats?->draft ?? 0),
            'projectsPendingSubmission' => $projectsPendingSubmission,
            'packageIds' => $packageIds,
            'packagesWithFlaggedPhotos' => $packagesWithFlaggedPhotos,
            'packagesNeedingRework' => $packagesNeedingRework,
            'packagesReadyToSubmit' => $packagesReadyToSubmit,
        ];
    }

    /**
     * @param  Builder<CloseoutPackage>  $packages
     * @param  list<int>  $projectIds
     * @param  list<int>  $packageIds
     * @return array{
     *     photosCaptured: int,
     *     photosUploaded: int,
     *     flaggedPhotos: int,
     *     photosAwaitingReview: int,
     *     photosRequired: int,
     *     openIssues: int,
     *     recentSubmissions: int,
     * }
     */
    private function photoAndIssueStats(Builder $packages, array $projectIds, array $packageIds, bool $isCustomer = false): array
    {
        if ($isCustomer) {
            /** @var list<int> $customerPackageIds */
            $customerPackageIds = $projectIds !== [] ? (clone $packages)
                ->whereIn('status', CloseoutPackage::CUSTOMER_ACCESSIBLE_STATUSES)
                ->whereNull('locked_at')
                ->pluck('id')
                ->all() : [];

            $approvedCount = $customerPackageIds !== [] ? Photo::query()
                ->whereIn('closeout_package_id', $customerPackageIds)
                ->whereNull('replaced_at')
                ->where('is_approved', true)
                ->count() : 0;

            return [
                'photosCaptured' => $approvedCount,
                'photosUploaded' => $approvedCount,
                'flaggedPhotos' => 0,
                'photosAwaitingReview' => 0,
                'photosRequired' => $this->requiredPhotoSlots($customerPackageIds),
                'openIssues' => 0,
                'recentSubmissions' => 0,
            ];
        }
        $photoStats = $packageIds !== [] ? Photo::query()
            ->whereIn('closeout_package_id', $packageIds)
            ->selectRaw('
                COUNT(*) as total_captured,
                SUM(CASE WHEN replaced_at IS NULL THEN 1 ELSE 0 END) as uploaded,
                SUM(CASE WHEN replaced_at IS NULL AND status IN (?, ?) THEN 1 ELSE 0 END) as flagged,
                SUM(CASE WHEN replaced_at IS NULL AND status = ? THEN 1 ELSE 0 END) as awaiting_review
            ', [
                PhotoStatus::FLAGGED->value,
                PhotoStatus::REPLACEMENT_REQUESTED->value,
                PhotoStatus::PENDING->value,
            ])
            ->first() : null;

        $openIssues = $projectIds !== [] ? IssueHistory::query()
            ->whereIn('project_id', $projectIds)
            ->whereNull('resolved_at')
            ->count() : 0;

        $recentSubmissions = $projectIds !== [] ? (clone $packages)
            ->where('submitted_at', '>=', now()->subDays(7))
            ->count() : 0;

        return [
            'photosCaptured' => (int) ($photoStats?->total_captured ?? 0),
            'photosUploaded' => (int) ($photoStats?->uploaded ?? 0),
            'flaggedPhotos' => (int) ($photoStats?->flagged ?? 0),
            'photosAwaitingReview' => (int) ($photoStats?->awaiting_review ?? 0),
            'photosRequired' => $this->requiredPhotoSlots($packageIds),
            'openIssues' => $openIssues,
            'recentSubmissions' => $recentSubmissions,
        ];
    }

    /**
     * Projects staffed with a manager and at least one supervisor that are
     * still active work (not completed or archived).
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    private function staffedProjects(Builder $query): Builder
    {
        return $query
            ->whereNotNull('manager_id')
            ->whereHas('fieldSupervisors')
            ->whereNotIn('status', self::INACTIVE_PROJECT_STATUSES);
    }

    /**
     * @return Builder<Project>
     */
    private function scopedProjects(User $user): Builder
    {
        return match (true) {
            $user->hasRole(Role::ADMIN->value) => Project::query(),
            $user->hasRole(Role::MANAGER->value) => Project::query()->where('manager_id', $user->id),
            $user->hasRole(Role::CUSTOMER->value) => $this->customerScope->accessibleProjects($user),
            default => Project::query()->whereHas(
                'fieldSupervisors',
                fn (Builder $query): Builder => $query->whereKey($user->id)
            ),
        };
    }

    /**
     * @return list<int>
     */
    private function supervisorIdsForManager(User $user): array
    {
        /** @var list<int> $projectIds */
        $projectIds = (clone $this->scopedProjects($user))->pluck('id')->all();

        if ($projectIds === []) {
            return [];
        }

        /** @var list<int> */
        return DB::table('project_field_supervisors')
            ->whereIn('project_id', $projectIds)
            ->pluck('user_id')
            ->values()
            ->all();
    }

    /**
     * Health distribution per market for the color-coded market card.
     * Buckets mirror the lifecycle buckets, so every non-archived project
     * lands in exactly one segment per market.
     *
     * @param  Builder<Project>  $base
     * @return list<array{market_id: int|null, market: string, count: int, completed: int, in_progress: int, not_started: int, attention: int}>
     */
    private function marketHealth(Builder $base): array
    {
        /** @var Collection<int, Project> $projects */
        $projects = (clone $base)
            ->whereNotIn('status', [ProjectStatus::ARCHIVED->value])
            ->with(['market', 'fieldSupervisors'])
            ->get();

        /** @var list<array{market_id: int|null, market: string, count: int, completed: int, in_progress: int, not_started: int, attention: int}> */
        return $projects
            ->groupBy(fn (Project $project) => $project->market_id ?? 0)
            ->map(function (Collection $group): array {
                $market = $group->first()?->market;

                return [
                    'market_id' => $market !== null ? $market->id : null,
                    'market' => $market !== null ? $market->name : 'Unknown',
                    'count' => $group->count(),
                    'completed' => $group->filter(fn (Project $p): bool => $this->projectBucket($p) === 'completed')->count(),
                    'in_progress' => $group->filter(fn (Project $p): bool => $this->projectBucket($p) === 'in_progress')->count(),
                    'not_started' => $group->filter(fn (Project $p): bool => $this->projectBucket($p) === 'not_started')->count(),
                    'attention' => $group->filter(fn (Project $p): bool => $this->projectBucket($p) === 'attention')->count(),
                ];
            })
            ->sortByDesc(fn (array $item) => $item['count'])
            ->values()
            ->all();
    }

    /**
     * Lifecycle bucket a project falls into; mirrors the dashboard lifecycle
     * cards so per-market bars stay consistent with the global numbers.
     */
    private function projectBucket(Project $project): string
    {
        if ($project->status === ProjectStatus::COMPLETED || (int) $project->progress_percentage >= 100) {
            return 'completed';
        }

        $staffed = $project->manager_id !== null && $project->fieldSupervisors->isNotEmpty();
        if (! $staffed) {
            return 'attention';
        }

        return (int) $project->progress_percentage === 0 ? 'not_started' : 'in_progress';
    }

    /**
     * Staffed, active projects whose work has gone quiet (see Project scope).
     *
     * @param  Builder<Project>  $base
     */
    private function stalledProjects(Builder $base): int
    {
        return (clone $base)->stalled()->count();
    }

    /**
     * Per-day submission and approval counts for the last 30 days.
     *
     * @param  list<int>  $projectIds
     * @return list<array{date: string, submissions: int, approvals: int}>
     */
    private function momentum(array $projectIds): array
    {
        if ($projectIds === []) {
            /** @var list<array{date: string, submissions: int, approvals: int}> $emptyDays */
            $emptyDays = [];
            for ($i = 29; $i >= 0; $i--) {
                $emptyDays[] = [
                    'date' => now()->subDays($i)->toDateString(),
                    'submissions' => 0,
                    'approvals' => 0,
                ];
            }

            return $emptyDays;
        }

        $cutoff = now()->subDays(29)->startOfDay();
        $packages = CloseoutPackage::query()->whereIn('project_id', $projectIds);

        /** @var Collection<string, int> $submissions */
        $submissions = (clone $packages)
            ->where('submitted_at', '>=', $cutoff)
            ->selectRaw('DATE(submitted_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        /** @var Collection<string, int> $approvals */
        $approvals = (clone $packages)
            ->where('approved_at', '>=', $cutoff)
            ->selectRaw('DATE(approved_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        /** @var list<array{date: string, submissions: int, approvals: int}> $days */
        $days = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $days[] = [
                'date' => $date,
                'submissions' => (int) ($submissions[$date] ?? 0),
                'approvals' => (int) ($approvals[$date] ?? 0),
            ];
        }

        return $days;
    }

    /**
     * Total required photo slots across the scoped packages.
     *
     * @param  list<int>  $packageIds
     */
    private function requiredPhotoSlots(array $packageIds): int
    {
        if ($packageIds === []) {
            return 0;
        }

        /** @var list<int> $stageIds */
        $stageIds = PackageStage::query()
            ->whereIn('closeout_package_id', $packageIds)
            ->pluck('id')
            ->all();

        if ($stageIds === []) {
            return 0;
        }

        return (int) PackageCategorySelection::query()
            ->whereIn('package_stage_id', $stageIds)
            ->where('is_selected', true)
            ->whereHas('templatePictureCategory', function (Builder $query): void {
                $query->where('is_required', true)->whereNull('deleted_at');
            })
            ->sum('picture_count');
    }

    /**
     * Email volume for today — only meaningful to admins (all projects)
     * and managers (their own projects).
     *
     * @param  list<int>  $projectIds
     * @return array{int, int} [sent today, failed today]
     */
    private function emailsToday(User $user, array $projectIds): array
    {
        if (! $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]) || $projectIds === []) {
            return [0, 0];
        }

        $emailCounts = EmailLog::query()
            ->whereIn('project_id', $projectIds)
            ->selectRaw('
                SUM(CASE WHEN status = ? AND sent_at >= ? THEN 1 ELSE 0 END) as sent_today,
                SUM(CASE WHEN status = ? AND created_at >= ? THEN 1 ELSE 0 END) as failed_today
            ', [
                EmailStatus::SENT->value,
                now()->startOfDay(),
                EmailStatus::FAILED->value,
                now()->startOfDay(),
            ])
            ->first();

        return [
            (int) ($emailCounts?->sent_today ?? 0),
            (int) ($emailCounts?->failed_today ?? 0),
        ];
    }

    /**
     * @param  Closure(): DashboardStatsDTO  $callback
     */
    private function remember(User $user, Closure $callback): DashboardStatsDTO
    {
        if (! Cache::supportsTags()) {
            return $callback();
        }

        return Cache::tags(['dashboard', "user.{$user->id}"])
            ->remember('stats', self::CACHE_TTL_SECONDS, $callback);
    }
}
