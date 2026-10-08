<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Services;

use App\Domains\ChangeOrders\Enums\ChangeOrderStatus;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CustomerPortal\Services\CustomerPortalScopeService;
use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;

/**
 * The one place that knows how a feedback round maps back to the thing it is
 * about. Every entity type routes through the project's portal access rules, so
 * a customer can only ever reach a round belonging to a project they were
 * granted — and adding an entity type is a single change here rather than a
 * change in every action, notification and policy.
 */
final class FeedbackEntityResolver
{
    public function __construct(
        private readonly CustomerPortalScopeService $scopeService,
    ) {}

    /**
     * The project the round's entity belongs to, or null when the entity has
     * since been deleted.
     */
    public function projectFor(EntityFeedbackRound $round): ?Project
    {
        $entity = $this->entityFor($round);

        if ($entity === null) {
            return null;
        }

        return $entity instanceof Project ? $entity : $entity->project;
    }

    /**
     * The project manager who receives this round's notifications.
     */
    public function managerFor(EntityFeedbackRound $round): ?User
    {
        return $this->projectFor($round)?->manager;
    }

    /**
     * Whether a customer has been granted access to the project behind the
     * round. Admins and managers are not customers and are gated by role
     * elsewhere; this is the customer-side scope check the policy needs and
     * previously did not have.
     */
    /**
     * Whether the review loop is over because the customer has ruled on the
     * entity itself.
     *
     * Once a change order is approved, partially approved or rejected there is
     * nothing left to negotiate: continuing to collect annotations would invite
     * feedback on a decision that is already final. Only change orders can reach
     * a decision today, so this stays narrow on purpose.
     */
    public function entityIsDecided(EntityFeedbackRound $round): bool
    {
        $entity = $this->entityFor($round);

        if (! $entity instanceof ChangeOrder) {
            return false;
        }

        return in_array($entity->status->value, [
            ChangeOrderStatus::APPROVED->value,
            ChangeOrderStatus::PARTIALLY_APPROVED->value,
            ChangeOrderStatus::REJECTED->value,
        ], true);
    }

    public function customerCanAccess(User $user, EntityFeedbackRound $round): bool
    {
        $project = $this->projectFor($round);

        return $project !== null && $this->scopeService->hasProjectAccess($user, $project);
    }

    private function entityFor(EntityFeedbackRound $round): ChangeOrder|IssueHistory|CloseoutPackage|Project|null
    {
        return match ($round->entity_type) {
            FeedbackEntityType::CHANGE_ORDER => ChangeOrder::query()
                ->with('project')
                ->find($round->entity_id),
            FeedbackEntityType::ISSUE => IssueHistory::query()
                ->with('project')
                ->find($round->entity_id),
            FeedbackEntityType::CLOSEOUT_PACKAGE => CloseoutPackage::query()
                ->with('project')
                ->find($round->entity_id),
        };
    }
}
