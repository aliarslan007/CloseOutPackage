<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Actions;

use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\ScopeOfWork\Notifications\SowReportSubmittedNotification;
use App\Domains\ScopeOfWork\Services\SowNotificationRecipientResolver;
use App\Domains\Users\Models\User;
use App\Shared\Exceptions\ChecklistIncompleteException;
use App\Shared\Exceptions\InvalidStateTransitionException;

final class SubmitSowForReviewAction
{
    public function __construct(
        private readonly SowNotificationRecipientResolver $recipientResolver,
    ) {}

    public function execute(SowReport $report, ?User $actor = null): SowReport
    {
        if (! $report->status->canTransitionTo(SowStatus::IN_REVIEW)) {
            throw new InvalidStateTransitionException('Only draft SOWs can be submitted for review.');
        }

        // §4.3 / §9.15 — every required checklist question must carry a response.
        $unanswered = $report->checklistResponses()
            ->whereHas('item', fn ($query) => $query->where('is_required', true))
            ->whereNull('response')
            ->count();

        if ($unanswered > 0) {
            throw new ChecklistIncompleteException(
                sprintf('%d required checklist item(s) are unanswered.', $unanswered),
            );
        }

        $isResubmission = $report->reopened_at !== null;
        $submitter = $actor ?? $report->creator;

        $report->update([
            'status' => SowStatus::IN_REVIEW,
            'submitted_at' => now(),
        ]);

        if ($submitter !== null) {
            $managers = $this->recipientResolver->resolveManagersFor($report, $submitter);
            foreach ($managers as $manager) {
                $manager->notify(new SowReportSubmittedNotification($report, $submitter, $isResubmission));
            }
        }

        return $report;
    }
}
