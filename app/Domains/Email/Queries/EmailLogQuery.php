<?php

declare(strict_types=1);

namespace App\Domains\Email\Queries;

use App\Domains\Email\Enums\EmailStatus;
use App\Domains\Email\Enums\EmailType;
use App\Domains\Email\Models\EmailLog;
use App\Domains\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class EmailLogQuery
{
    /**
     * Whitelisted sort keys mapped to [column, direction].
     * sent_at is null for pending emails, so the time-based sorts fall back to created_at.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const SORTS = [
        'newest' => ['sent_at', 'desc'],
        'oldest' => ['sent_at', 'asc'],
        'recipient_asc' => ['recipient_name', 'asc'],
        'recipient_desc' => ['recipient_name', 'desc'],
        'sender_asc' => ['sender_name', 'asc'],
        'sender_desc' => ['sender_name', 'desc'],
        'subject_asc' => ['subject', 'asc'],
        'subject_desc' => ['subject', 'desc'],
        'scheduled' => ['scheduled_at', 'asc'],
    ];

    private const DEFAULT_SORT = 'newest';

    /**
     * @return list<string>
     */
    public static function sortKeys(): array
    {
        return array_keys(self::SORTS);
    }

    /**
     * @param  array{search?: ?string, date_from?: ?string, date_to?: ?string, sort?: ?string}  $filters
     * @return LengthAwarePaginator<int, EmailLog>
     */
    public function forAdmin(array $filters = []): LengthAwarePaginator
    {
        return $this->applyFilters(EmailLog::query(), $filters)->paginate(50);
    }

    /**
     * @param  array{search?: ?string, date_from?: ?string, date_to?: ?string, sort?: ?string}  $filters
     * @return LengthAwarePaginator<int, EmailLog>
     */
    public function forManager(User $user, array $filters = []): LengthAwarePaginator
    {
        return $this->applyFilters(
            EmailLog::query()->whereIn('project_id', $user->managedProjects()->pluck('projects.id')),
            $filters,
        )->paginate(50);
    }

    /**
     * The Customer Emails outbox: manager-visible customer email intents.
     * Scoped exactly like forManager (the policy grants row actions on the
     * same set), filtered to outbox-actionable types and excluding staff
     * delivery rows, with the outbox default sort of soonest-scheduled first.
     *
     * Sent and cancelled rows stay visible so a manager can resend a fresh
     * copy from the same surface (with the duplicate warning); only
     * SUPPRESSED rows live on the audit pages alone.
     *
     * @param  array{search?: ?string, status?: ?string, type?: ?string, project_id?: ?int}  $filters
     * @return LengthAwarePaginator<int, EmailLog>
     */
    public function outboxForManager(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = EmailLog::query()
            ->whereIn('project_id', $user->managedProjects()->pluck('projects.id'))
            ->whereIn('type', array_map(fn (EmailType $t): string => $t->value, EmailType::manualTypes()))
            ->whereIn('status', [
                EmailStatus::READY->value,
                EmailStatus::SCHEDULED->value,
                EmailStatus::PENDING->value,
                EmailStatus::SENT->value,
                EmailStatus::FAILED->value,
                EmailStatus::CANCELLED->value,
            ]);

        return $this->applyOutboxFilters($query, $filters)->paginate(50);
    }

    /**
     * The outbox for admins — same as outboxForManager without the market
     * scope (policy grants admins every row).
     *
     * @param  array{search?: ?string, status?: ?string, type?: ?string, project_id?: ?int}  $filters
     * @return LengthAwarePaginator<int, EmailLog>
     */
    public function outboxForAdmin(array $filters = []): LengthAwarePaginator
    {
        $query = EmailLog::query()
            ->whereIn('type', array_map(fn (EmailType $t): string => $t->value, EmailType::manualTypes()))
            ->whereIn('status', [
                EmailStatus::READY->value,
                EmailStatus::SCHEDULED->value,
                EmailStatus::PENDING->value,
                EmailStatus::SENT->value,
                EmailStatus::FAILED->value,
                EmailStatus::CANCELLED->value,
            ]);

        return $this->applyOutboxFilters($query, $filters)->paginate(50);
    }

    /**
     * @param  Builder<EmailLog>  $query
     * @param  array{search?: ?string, status?: ?string, type?: ?string, project_id?: ?int}  $filters
     * @return Builder<EmailLog>
     */
    private function applyOutboxFilters(Builder $query, array $filters): Builder
    {
        $search = $filters['search'] ?? null;
        if (is_string($search) && $search !== '') {
            $query->where(function (Builder $sub) use ($search): void {
                $sub->where('recipient_email', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        $status = $filters['status'] ?? null;
        if (is_string($status) && EmailStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        }

        $type = $filters['type'] ?? null;
        if (is_string($type) && EmailType::tryFrom($type) !== null) {
            $query->where('type', $type);
        }

        $projectId = $filters['project_id'] ?? null;
        if (is_numeric($projectId)) {
            $query->where('project_id', (int) $projectId);
        }

        // Upcoming schedules first, then anything else newest-first.
        return $query
            ->with(['project', 'changeOrder'])
            ->orderByRaw('CASE WHEN status = ? AND scheduled_at IS NOT NULL THEN 0 ELSE 1 END', [EmailStatus::SCHEDULED->value])
            ->orderByRaw('COALESCE(scheduled_at, created_at) desc');
    }

    /**
     * @param  LengthAwarePaginator<int, EmailLog>  $paginator
     * @return array{current_page: int, last_page: int, total: int, per_page: int, from: ?int, to: ?int}
     */
    public static function paginatorMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    /**
     * @param  Builder<EmailLog>  $query
     * @param  array{search?: ?string, date_from?: ?string, date_to?: ?string, sort?: ?string}  $filters
     * @return Builder<EmailLog>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        $search = $filters['search'] ?? null;
        if (is_string($search) && $search !== '') {
            $query->where(function (Builder $sub) use ($search): void {
                $sub->where('recipient_email', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%")
                    ->orWhere('sender_email', 'like', "%{$search}%")
                    ->orWhere('sender_name', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        $dateFrom = $filters['date_from'] ?? null;
        if (is_string($dateFrom) && $dateFrom !== '') {
            $query->whereDate('sent_at', '>=', $dateFrom);
        }

        $dateTo = $filters['date_to'] ?? null;
        if (is_string($dateTo) && $dateTo !== '') {
            $query->whereDate('sent_at', '<=', $dateTo);
        }

        $query->with(['project', 'package']);

        $sortKey = is_string($filters['sort'] ?? null) && isset(self::SORTS[$filters['sort']])
            ? $filters['sort']
            : self::DEFAULT_SORT;

        [$column, $direction] = self::SORTS[$sortKey];

        if ($column === 'sent_at') {
            return $query->orderByRaw('COALESCE(sent_at, created_at) '.$direction);
        }

        return $query->orderBy($column, $direction);
    }
}
