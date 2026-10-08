<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Actions;

use App\Domains\Approval\Enums\ApprovalDecision;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use App\Domains\EntityFeedback\Events\FeedbackResolvedByManager;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\EntityFeedback\Notifications\FeedbackResponseReadyNotification;
use App\Domains\EntityFeedback\Notifications\FeedbackRevisionApprovedNotification;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Photos\Models\Photo;
use App\Domains\Users\Models\User;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\DB;

final class ResolveAllFeedbackNotesAction
{
    public function execute(
        EntityFeedbackRound $round,
        User $actor,
        ?string $generalResponse = null,
    ): EntityFeedbackRound {
        if (! $round->isSubmitted()) {
            throw new InvalidStateTransitionException(
                'Only submitted feedback rounds can be resolved and sent to the customer.'
            );
        }

        return DB::transaction(function () use ($round, $actor, $generalResponse): EntityFeedbackRound {
            $now = now();

            if ($generalResponse !== null && trim($generalResponse) !== '') {
                $round->notes()
                    ->whereNull('manager_response')
                    ->whereNull('resolved_at')
                    ->whereNull('withdrawn_at')
                    ->update([
                        'manager_response' => trim($generalResponse),
                        'resolved_at' => $now,
                        'resolved_by' => $actor->id,
                    ]);
            }

            // Stamp resolution timestamp and actor on any unresolved notes.
            // A withdrawn issue is skipped: the customer dropped it, so there is
            // nothing here to answer - labelling it "resolved" would erase their
            // "withdrawn by customer" record.
            $round->notes()
                ->whereNull('resolved_at')
                ->whereNull('withdrawn_at')
                ->update([
                    'resolved_at' => $now,
                    'resolved_by' => $actor->id,
                ]);

            // This is the moment the answers become the customer's to read, so
            // it is stamped per note. Everything written before this point was a
            // draft and stays invisible until here.
            $round->notes()
                ->whereNull('response_sent_at')
                ->whereNull('withdrawn_at')
                ->update(['response_sent_at' => $now]);

            $round->update([
                'status' => FeedbackRoundStatus::RESOLVED,
                'resolved_at' => $now,
                'resolved_by' => $actor->id,
            ]);

            if ($round->entity_type === FeedbackEntityType::CLOSEOUT_PACKAGE) {
                /** @var CloseoutPackage|null $package */
                $package = CloseoutPackage::query()->find($round->entity_id);
                if ($package !== null) {
                    $package->photos()
                        ->whereNull('replaced_at')
                        ->where(function ($query): void {
                            $query->where('status', PhotoStatus::PENDING->value)
                                ->orWhereNull('is_approved')
                                ->orWhere('is_approved', false);
                        })
                        ->get()
                        ->each(function (Photo $photo) use ($actor, $now): void {
                            $photo->update([
                                'status' => PhotoStatus::APPROVED,
                                'is_approved' => true,
                                'approved_at' => $now,
                                'approved_by' => $actor->id,
                            ]);

                            $photo->photoApproval()->updateOrCreate(
                                ['photo_id' => $photo->id],
                                [
                                    'reviewed_by' => $actor->id,
                                    'decision' => ApprovalDecision::APPROVED,
                                    'reason' => 'Approved upon feedback resolution by manager.',
                                ]
                            );
                        });
                }
            }

            FeedbackResolvedByManager::dispatch($round, $actor);

            $customer = $round->submittedBy ?? $round->openedBy;
            $customer?->notify(new FeedbackResponseReadyNotification($round, $actor));

            if ($round->assigned_to_user_id !== null) {
                $technician = User::query()->find($round->assigned_to_user_id);
                $technician?->notify(new FeedbackRevisionApprovedNotification($round, $actor));
            } elseif ($round->technician_submitted_by !== null) {
                $technician = User::query()->find($round->technician_submitted_by);
                $technician?->notify(new FeedbackRevisionApprovedNotification($round, $actor));
            }

            return $round;
        });
    }
}
