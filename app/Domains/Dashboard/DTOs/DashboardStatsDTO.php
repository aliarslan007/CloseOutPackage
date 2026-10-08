<?php

declare(strict_types=1);

namespace App\Domains\Dashboard\DTOs;

final readonly class DashboardStatsDTO
{
    /**
     * @param  list<array{market_id: int|null, market: string, count: int, completed: int, in_progress: int, not_started: int, attention: int}>  $marketHealth
     * @param  list<array{date: string, submissions: int, approvals: int}>  $momentum
     * @param  list<array{key: string, label: string, count: int, href: string, tone: string}>  $attentionItems
     * @param  list<array{id: string, label: string, project: string, href: string, meta: string, created_at: string|null}>  $customerActivity
     * @param  list<array{id: int, name: string, status: string, progress: int, href: string}>  $customerProjects
     * @param  list<array{id: int, title: string, project: string, amount: string, href: string, status: string}>  $customerAttention
     * @param  list<array{id: int, url: string, full_url: string, project: string, captured_at: string|null}>  $recentPhotos
     * @param  array{technicians: int, project: string|null, href: string|null, updated_at: string|null}  $fieldActivity
     * @param  list<array{id: int, hash_id: string, title: string, project_name: string, status: string, status_label: string, total_amount: string, created_at: string|null, href: string}>  $recentChangeOrders
     * @param  array<string, bool>  $sectionVisible  Role-scoped section visibility flags for the frontend.
     */
    public function __construct(
        public int $totalProjects,
        public int $projectsPendingSubmission,
        public int $completedProjects,
        public int $pendingApprovals,
        public int $flaggedPhotos,
        public int $photosAwaitingReview,
        public int $openIssues,
        public int $avgProgress,
        public int $recentSubmissions,
        public array $marketHealth,
        // Project lifecycle — mutually exclusive buckets over non-archived projects.
        public int $projectsNeedingManager,
        public int $projectsNeedingSupervisor,
        public int $projectsNotStarted,
        public int $projectsInProgress,
        public int $projectsStalled,
        // Package pipeline.
        public int $totalPackages,
        public int $packagesApproved,
        public int $packagesReadyToSubmit,
        public int $packagesNeedingRework,
        public int $packagesRejected,
        public int $packagesWithFlaggedPhotos,
        public int $packagesDraft,
        // Media & communication.
        public int $photosCaptured,
        public int $photosUploaded,
        public int $photosRequired,
        public int $emailsSentToday,
        public int $emailsFailedToday,
        // Last-30-days submission/approval activity per day.
        public array $momentum,
        // Role-scoped action items for the "needs your attention" panel.
        public array $attentionItems,
        public array $customerActivity,
        public array $customerProjects,
        public array $customerAttention,
        public array $recentPhotos,
        public array $fieldActivity,
        // Change Orders metrics & recent items.
        public int $totalChangeOrders,
        public int $changeOrdersDraft,
        public int $changeOrdersPendingCustomer,
        public int $changeOrdersApproved,
        public int $changeOrdersRejected,
        public float $changeOrdersTotalValue,
        public float $changeOrdersApprovedValue,
        public array $recentChangeOrders,
        // Section visibility — keyed by section name, false = hidden for this role.
        public array $sectionVisible,
    ) {}
}
