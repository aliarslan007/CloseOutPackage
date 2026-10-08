<?php

declare(strict_types=1);

namespace App\Domains\Email\Actions;

use App\Domains\ChangeOrders\Jobs\SendChangeOrderEmailJob;
use App\Domains\Email\EmailScheduleInPastException;
use App\Domains\Email\EmailScheduleTooFarAheadException;
use App\Domains\Email\Enums\EmailStatus;
use App\Domains\Email\Enums\EmailType;
use App\Domains\Email\InvalidOutboxTransitionException;
use App\Domains\Email\Jobs\SendCopEmailJob;
use App\Domains\Email\Models\EmailLog;
use App\Domains\Users\Models\User;
use App\Shared\Exceptions\EmailNotConfiguredException;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The manager-facing half of the Customer Emails outbox: release, schedule,
 * cancel and resend intents recorded by SendCopEmailAction.
 *
 * All writes go through a per-row status guard (an atomic conditional update)
 * so a double-click or a delayed job firing late can never double-send.
 */
final class DeliverCustomerEmailAction
{
    /** Hard cap on how far out an email may be scheduled. */
    public const MAX_SCHEDULE_DAYS = 30;

    /**
     * Release a READY intent immediately.
     */
    public function sendNow(EmailLog $log, User $actor): EmailLog
    {
        $this->guardOpen($log, 'send');

        if (! $this->customerEmailsEnabled() && $log->type->isCustomerFacing()) {
            throw new EmailNotConfiguredException(
                $log->type,
                'Customer emails are currently disabled (CUSTOMER_EMAILS_ENABLED=false).',
            );
        }

        $released = $this->transition($log, EmailStatus::PENDING, [
            'created_by' => $actor->id,
            'scheduled_at' => null,
        ]);

        if ($released === null) {
            throw new InvalidOutboxTransitionException($log, 'send');
        }

        $this->dispatchDelivery($released);

        return $released;
    }

    /**
     * Release a READY intent for a future send. The raw change-order token
     * (if any) only ever lives in the queued job payload, never in the row.
     */
    public function schedule(EmailLog $log, CarbonInterface $sendAt, User $actor): EmailLog
    {
        $this->guardOpen($log, 'schedule');

        if ($sendAt->isPast()) {
            throw new EmailScheduleInPastException($sendAt);
        }

        if ($sendAt->gt(now()->addDays(self::MAX_SCHEDULE_DAYS))) {
            throw new EmailScheduleTooFarAheadException($sendAt, self::MAX_SCHEDULE_DAYS);
        }

        if (! $this->customerEmailsEnabled() && $log->type->isCustomerFacing()) {
            throw new EmailNotConfiguredException(
                $log->type,
                'Customer emails are currently disabled (CUSTOMER_EMAILS_ENABLED=false).',
            );
        }

        $scheduled = $this->transition($log, EmailStatus::SCHEDULED, [
            'created_by' => $actor->id,
            'scheduled_at' => $sendAt,
        ]);

        if ($scheduled === null) {
            throw new InvalidOutboxTransitionException($log, 'schedule');
        }

        $this->dispatchDelivery($scheduled, $sendAt);

        return $scheduled;
    }

    /**
     * Call off a scheduled (or pending) send. The already-dispatched delayed
     * job no-ops against the row's new status when it eventually fires.
     */
    public function cancel(EmailLog $log, User $actor): EmailLog
    {
        if ($log->status === EmailStatus::CANCELLED) {
            return $log; // idempotent: cancelling twice is a no-op
        }

        if (! $log->status->isOpen()) {
            throw new InvalidOutboxTransitionException($log, 'cancel');
        }

        $cancelled = $this->transition($log, EmailStatus::CANCELLED, [
            'created_by' => $actor->id,
            'cancelled_at' => now(),
        ]);

        if ($cancelled === null) {
            throw new InvalidOutboxTransitionException($log, 'cancel');
        }

        return $cancelled;
    }

    /**
     * Send a fresh copy of an already-sent (or failed/cancelled/suppressed)
     * email. Creates a NEW row — the original keeps its sent_at and audit
     * trail intact — with an attempt-suffixed idempotency key so the unique
     * index accepts it and the job's duplicate guard still applies to it.
     */
    public function resend(EmailLog $original, User $actor): EmailLog
    {
        if (! $original->status->canResend()) {
            throw new InvalidOutboxTransitionException($original, 'resend');
        }

        if (! $this->customerEmailsEnabled() && $original->type->isCustomerFacing()) {
            throw new EmailNotConfiguredException(
                $original->type,
                'Customer emails are currently disabled (CUSTOMER_EMAILS_ENABLED=false).',
            );
        }

        return DB::transaction(function () use ($original, $actor): EmailLog {
            $attempt = (int) EmailLog::query()
                ->where('context_id', $original->context_id)
                ->where('type', $original->type->value)
                ->where('recipient_email', $original->recipient_email)
                ->count();

            $copy = $original->replicate(['id', 'created_at', 'updated_at']);
            $copy->status = EmailStatus::READY;
            $copy->sent_at = null;
            $copy->scheduled_at = null;
            $copy->cancelled_at = null;
            $copy->provider_message_id = null;
            $copy->failed_reason = null;
            $copy->created_by = $actor->id;
            $copy->idempotency_key = $this->resendKey($original, $attempt);
            $copy->save();

            return $this->sendNow($copy, $actor);
        });
    }

    /**
     * Retry a FAILED delivery in place (the send itself failed; nothing was
     * received). Re-enters the queue without creating a new audit row.
     */
    public function retry(EmailLog $log, User $actor): EmailLog
    {
        if ($log->status !== EmailStatus::FAILED) {
            throw new InvalidOutboxTransitionException($log, 'retry');
        }

        if (! $this->customerEmailsEnabled() && $log->type->isCustomerFacing()) {
            throw new EmailNotConfiguredException(
                $log->type,
                'Customer emails are currently disabled (CUSTOMER_EMAILS_ENABLED=false).',
            );
        }

        $retried = $this->transition($log, EmailStatus::PENDING, [
            'created_by' => $actor->id,
            'failed_reason' => null,
        ]);

        if ($retried === null) {
            throw new InvalidOutboxTransitionException($log, 'retry');
        }

        $this->dispatchDelivery($retried);

        return $retried;
    }

    /**
     * Sweep due scheduled intents (scheduled_at <= now()) that were not yet picked
     * up by a delayed worker (e.g. queue restart or cPanel cron runner).
     *
     * @return int count of released emails
     */
    public function processDue(): int
    {
        if (! $this->customerEmailsEnabled()) {
            return 0;
        }

        $dueLogs = EmailLog::query()
            ->where('status', EmailStatus::SCHEDULED->value)
            ->where('scheduled_at', '<=', now())
            ->get();

        $processed = 0;

        foreach ($dueLogs as $log) {
            $released = $this->transition($log, EmailStatus::PENDING, [
                'scheduled_at' => null,
            ]);

            if ($released !== null) {
                $this->dispatchDelivery($released);
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Hand the row to the queue. Change-order rows carry no payload — their
     * DTO (and the raw decision token) is built inside their dedicated job —
     * so they route to SendChangeOrderEmailJob; everything else hydrates
     * from the stored payload in SendCopEmailJob.
     *
     * Delayed dispatches snapshot the row's scheduled_at: if the row is
     * rescheduled later, the superseded job's snapshot no longer matches and
     * it no-ops on arrival (on top of the status guard).
     */
    private function dispatchDelivery(EmailLog $log, ?CarbonInterface $delay = null): void
    {
        $expectedScheduledAt = $delay !== null ? $log->scheduled_at?->toIso8601String() : null;

        if ($log->type === EmailType::CHANGE_ORDER && $log->change_order_id !== null) {
            $pending = SendChangeOrderEmailJob::dispatch($log, $expectedScheduledAt);
        } else {
            $pending = SendCopEmailJob::dispatch($log, null, $expectedScheduledAt);
        }

        if ($delay !== null) {
            $pending->delay($delay);
        }
    }

    /**
     * Atomic READY→PENDING / READY→SCHEDULED / FAILED→PENDING transition.
     * Returns null when another request changed the status first.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function transition(EmailLog $log, EmailStatus $to, array $attributes = []): ?EmailLog
    {
        $updated = EmailLog::query()
            ->whereKey($log->id)
            ->where('status', $log->status->value)
            ->update(['status' => $to->value] + $attributes);

        if ($updated === 0) {
            return null;
        }

        return $log->refresh();
    }

    private function guardOpen(EmailLog $log, string $action): void
    {
        if (! $log->status->isOpen()) {
            throw new InvalidOutboxTransitionException($log, $action);
        }
    }

    private function customerEmailsEnabled(): bool
    {
        return (bool) config('cop.customer_emails_enabled', false);
    }

    /**
     * The deterministic base key plus an attempt suffix, so a resend never
     * collides with the unique index while the first attempt keeps the plain
     * key (which is what makes "already sent" detection work).
     *
     * A sha256 key is exactly 64 chars — the column's width — so the base is
     * truncated by exactly the suffix length. The result always fits the
     * varchar(64) column, whatever the attempt number.
     */
    private function resendKey(EmailLog $original, int $attempt): ?string
    {
        $base = $original->idempotency_key;

        if ($base === null) {
            return null; // GENERAL-type emails have no key by design
        }

        $suffix = '|a:'.($attempt + 1);

        return substr($base, 0, 64 - strlen($suffix)).$suffix;
    }
}
