<?php

declare(strict_types=1);

namespace App\Domains\Projects\Queries;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Support\PackageLockFlags;
use App\Domains\EntityFeedback\Presenters\FeedbackRoundPresenter;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Projects\Models\IssueComment;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Presenters\ProjectDocumentPresenter;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class ProjectQuery
{
    public function __construct(
        private readonly FeedbackRoundPresenter $feedbackPresenter,
        private readonly ProjectDocumentPresenter $projectDocumentPresenter,
    ) {}

    private const PER_PAGE = 20;

    /** Columns the index list may be sorted by. */
    private const SORTABLE_COLUMNS = ['name', 'created_at'];

    /** Statuses excluded from the lifecycle assignment buckets. */
    private const INACTIVE_LIFECYCLE_STATUSES = [
        ProjectStatus::COMPLETED->value,
        ProjectStatus::ARCHIVED->value,
    ];

    /**
     * @return Collection<int, Project>
     */
    public function options(): Collection
    {
        return Project::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return Collection<int, Project>
     */
    public function accessibleOptions(User $user): Collection
    {
        $query = Project::query()->orderBy('name');

        if ($user->hasRole(Role::ADMIN->value)) {
            return $query->get(['id', 'name', 'market_id']);
        }

        if ($user->hasRole(Role::MANAGER->value)) {
            return $query->where(function (Builder $q) use ($user): void {
                $q->where('manager_id', $user->id)
                    ->orWhereHas('fieldSupervisors', fn (Builder $sub): Builder => $sub->whereKey($user->id));
            })->get(['id', 'name', 'market_id']);
        }

        return $query->whereHas('fieldSupervisors', fn (Builder $q): Builder => $q->whereKey($user->id))
            ->get(['id', 'name', 'market_id']);
    }

    /**
     * Project list for the index page with optional search, status, market,
     * package-status and lifecycle-assignment filters plus a whitelisted sort.
     *
     * @param  array{search?: ?string, status?: ?string, market_id?: ?int, package_status?: ?string, assignment?: ?string, sort?: ?string, direction?: ?string}  $filters
     * @return LengthAwarePaginator<int, Project>
     */
    public function forIndex(User $user, array $filters = []): LengthAwarePaginator
    {
        $projects = $this->scopedBaseQuery($user)
            ->with([
                'market',
                'customer',
                'customerEngineer.user.media',
                'customerAccesses.user.media',
                'manager.roles',
                'manager.media',
                'fieldSupervisors.roles',
                'fieldSupervisors.media',
                'closeoutPackages:id,project_id,status',
            ])
            ->withCount([
                'fieldSupervisors',
                'issues as open_issues_count' => fn (Builder $q): Builder => $q->whereNull('resolved_at'),
                // How many of this project's packages hold customer feedback nobody
                // has answered - the same awaitingReply() signal the package
                // listings flag, counted per project in the one query the table
                // already runs.
                'closeoutPackages as pending_feedback_packages_count' => fn (Builder $q): Builder => $q->whereHas(
                    'feedbackRounds',
                    fn (Builder $r): Builder => $r->awaitingReply(),
                ),
            ])
            ->when(
                is_string($filters['search'] ?? null) && $filters['search'] !== '',
                fn (Builder $query): Builder => $query->where('name', 'like', '%'.$filters['search'].'%'),
            )
            ->when(
                is_string($filters['status'] ?? null) && ProjectStatus::tryFrom($filters['status']) !== null,
                fn (Builder $query): Builder => $query->where('status', $filters['status']),
            )
            ->when(
                is_int($filters['market_id'] ?? null),
                fn (Builder $query): Builder => $query->where('market_id', $filters['market_id']),
            )
            ->when(
                is_string($filters['package_status'] ?? null) && PackageStatus::tryFrom($filters['package_status']) !== null,
                fn (Builder $query): Builder => $query->whereHas(
                    'closeoutPackages',
                    fn (Builder $q): Builder => $q->where('status', $filters['package_status']),
                ),
            )
            ->when(
                in_array($filters['assignment'] ?? null, self::LIFECYCLE_ASSIGNMENTS, true),
                fn (Builder $query): Builder => $this->applyAssignmentFilter($query, $filters['assignment']),
            )
            ->orderBy($this->sortColumn($filters), $this->sortDirection($filters))
            ->paginate(self::PER_PAGE);

        $projects->getCollection()->each->append('package_statuses');

        return $projects;
    }

    /**
     * Whitelisted lifecycle buckets matching the dashboard project-health card.
     */
    private const LIFECYCLE_ASSIGNMENTS = ['unassigned', 'needs_supervisor', 'not_started', 'in_progress', 'stalled'];

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    private function applyAssignmentFilter(Builder $query, ?string $assignment): Builder
    {
        return match ($assignment) {
            'unassigned' => $query
                ->whereNull('manager_id')
                ->whereNotIn('status', self::INACTIVE_LIFECYCLE_STATUSES),
            'needs_supervisor' => $query
                ->whereNotNull('manager_id')
                ->whereDoesntHave('fieldSupervisors')
                ->whereNotIn('status', self::INACTIVE_LIFECYCLE_STATUSES),
            'not_started' => $query
                ->whereNotNull('manager_id')
                ->whereHas('fieldSupervisors')
                ->whereNotIn('status', self::INACTIVE_LIFECYCLE_STATUSES)
                ->where(fn (Builder $q): Builder => $q
                    ->whereNull('progress_percentage')
                    ->orWhere('progress_percentage', 0)),
            'in_progress' => $query
                ->whereNotNull('manager_id')
                ->whereHas('fieldSupervisors')
                ->whereNotIn('status', self::INACTIVE_LIFECYCLE_STATUSES)
                ->whereBetween('progress_percentage', [1, 99]),
            'stalled' => $query->stalled(),
            default => $query,
        };
    }

    /**
     * @param  array{sort?: ?string, direction?: ?string}  $filters
     */
    private function sortColumn(array $filters): string
    {
        $sort = $filters['sort'] ?? null;

        return is_string($sort) && in_array($sort, self::SORTABLE_COLUMNS, true)
            ? $sort
            : 'created_at';
    }

    /**
     * @param  array{direction?: ?string}  $filters
     */
    private function sortDirection(array $filters): string
    {
        $direction = $filters['direction'] ?? null;

        return $direction === 'asc' ? 'asc' : 'desc';
    }

    /**
     * Markets that can be selected in the create form and the index list's
     * market filter.
     *
     * Derived from the same visibility scope as {@see forIndex()}, so the
     * dropdown never offers a market the user would filter down to an empty
     * list. A user with no market assignment (a manager before admin grants
     * access) still sees the markets of the projects they can see, so the
     * filter stays useful instead of silently offering nothing.
     *
     * Inactive markets are excluded from both: nothing new should be raised
     * against a deactivated region. Their existing projects stay in the
     * unfiltered list, so no project is ever hidden — it just cannot be
     * narrowed to by region.
     *
     * @return Collection<int, Market>
     */
    public function marketOptions(User $user): Collection
    {
        $ids = $this->scopedBaseQuery($user)
            ->whereNotNull('market_id')
            ->distinct()
            ->pluck('market_id');

        return Market::query()
            ->active()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * The visibility scope shared by the index list and its market filter.
     *
     * @return Builder<Project>
     */
    private function scopedBaseQuery(User $user): Builder
    {
        return Project::query()
            ->when(
                ! $this->isAdminOrManager($user),
                fn (Builder $query): Builder => $query->whereHas('fieldSupervisors', fn (Builder $q): Builder => $q->whereKey($user->id)),
            )
            ->when(
                $user->hasRole(Role::MANAGER->value),
                fn (Builder $query): Builder => $query->where('projects.manager_id', $user->id),
            );
    }

    public function show(Project $project, ?User $user = null): Project
    {
        $relations = [
            'market',
            'customerEngineer',
            'creator' => fn ($q) => $q->with('media')->withoutGlobalScopes(),
            'manager.roles',
            'manager.media',
            'fieldSupervisors.roles',
            'fieldSupervisors.media',
            'issues',
            'closeoutPackages' => function (HasMany $query) use ($user): HasMany {
                // Load the project back-reference so per-package authorization
                // (lock flags, delete) does not query the project per row.
                // lockedBy feeds the "locked by X on Y" attribution on the card.
                $query->with(['project:id,manager_id', 'lockedBy:id,name']);

                if ($user !== null && $user->hasRole(Role::CUSTOMER->value)) {
                    $query->whereIn('status', CloseoutPackage::CUSTOMER_ACCESSIBLE_STATUSES);
                }

                return $query;
            },
        ];

        $project->load($relations);

        if ($user !== null) {
            PackageLockFlags::attach($project->closeoutPackages, $user);
        }

        return $project;
    }

    public function issues(Project $project, ?User $viewer = null): Collection
    {
        $issues = $project->issues()
            ->with([
                'reporter.roles',
                'reporter.media',
                'resolver.roles',
                'resolver.media',
                'media',
                'comments' => fn ($query) => $query->oldest()->with('media'),
                'comments.author.roles',
                'comments.author.media',
            ])
            ->latest()
            ->get();

        $issues->each(function (IssueHistory $issue) use ($project, $viewer): void {
            $reporter = $issue->reporter;
            if ($reporter instanceof User) {
                $reporter->setAttribute('profile_photo_url', $reporter->profilePhotoUrl());
            }

            if ($issue->resolved_at !== null && $issue->resolver === null && $project->manager instanceof User) {
                $issue->setRelation('resolver', $project->manager);
            }

            $resolver = $issue->resolver;
            if ($resolver instanceof User) {
                $resolver->setAttribute('profile_photo_url', $resolver->profilePhotoUrl());
            }

            $issue->setAttribute('attachments', $this->mapMedia($issue));
            $issue->setAttribute('feedback_round', $this->feedbackPresenter->present($issue->latestFeedbackRound(), $viewer));
            $issue->setRelation('media', collect());

            $issue->comments->each(function (IssueComment $comment): void {
                $author = $comment->author;
                if ($author instanceof User) {
                    $author->setAttribute('profile_photo_url', $author->profilePhotoUrl());
                }

                $comment->setAttribute('attachments', $this->mapMedia($comment));
                $comment->setRelation('media', collect());
            });
        });

        return $issues;
    }

    /**
     * @return array<int, array{id: int, name: string, file_name: string, mime_type: string, size: int, url: string, original_url: string, preview_url: string, thumb_url: ?string}>
     */
    private function mapMedia(HasMedia $model): array
    {
        return $model->getMedia($model instanceof IssueHistory ? IssueHistory::MEDIA_COLLECTION : IssueComment::MEDIA_COLLECTION)
            ->map(fn (Media $media): array => [
                'id' => $media->id,
                'name' => $media->name,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type ?? '',
                'size' => $media->size,
                'url' => route('issues.attachments.show', $media->id),
                'original_url' => route('issues.attachments.show', $media->id),
                'preview_url' => route('issues.attachments.show', $media->id),
                'thumb_url' => route('issues.attachments.show', $media->id),
            ])->all();
    }

    public function sitePhoto(Project $project): ?Media
    {
        return $project->getFirstMedia(Project::SITE_PHOTO_COLLECTION);
    }

    /**
     * All six Project Documents Hub categories keyed by collection name, each
     * shaped by {@see ProjectDocumentPresenter}.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function projectDocuments(Project $project): array
    {
        return $this->projectDocumentPresenter->present($project);
    }

    private function isAdminOrManager(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]);
    }
}
