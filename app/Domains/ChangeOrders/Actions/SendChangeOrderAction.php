<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Actions;

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Notifications\ChangeOrderSentNotification;
use App\Domains\Email\Enums\EmailStatus;
use App\Domains\Email\Enums\EmailType;
use App\Domains\Email\Models\EmailLog;
use App\Domains\Settings\Services\MailSettingsService;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * The manager's send click: the change order leaves DRAFT here — status flips
 * to SENT_TO_CUSTOMER exactly once and each recipient gets a database
 * notification. The decision token is NOT minted and no email leaves the
 * system: one READY email_logs row per recipient is recorded and the email
 * itself only goes out when that row is released from the Customer Emails
 * outbox (SendChangeOrderEmailJob), which stays the single delivery path.
 */
final class SendChangeOrderAction
{
    public function __construct(
        private readonly BuildChangeOrderCustomerEmailAction $builder,
        private readonly MailSettingsService $mailSettings,
    ) {}

    public function execute(ChangeOrder $changeOrder): ChangeOrder
    {
        return DB::transaction(function () use ($changeOrder): ChangeOrder {
            $changeOrder->loadMissing(['items', 'project', 'creator']);

            // Edge case: nothing included → nothing to send.
            if (! $changeOrder->items()->where('is_included', true)->exists()) {
                throw new InvalidStateTransitionException(
                    'Add at least one included item before sending the change order.',
                );
            }

            // Edge case: no recipients → nothing to send to.
            if (! $changeOrder->recipients()->exists()) {
                throw new InvalidStateTransitionException(
                    'Select at least one customer recipient before sending.',
                );
            }

            $recipients = $changeOrder->recipients()->with('user')->get();
            $sender = $this->mailSettings->getSender();
            $subject = $this->builder->subject($changeOrder);

            $notifiedUsers = [];

            foreach ($recipients as $recipient) {
                if ($recipient->user === null) {
                    continue; // orphaned recipient row — nothing to email
                }

                $this->recordIntent($changeOrder, $recipient->user, $subject, $sender);
                $notifiedUsers[] = $recipient->user;
            }

            // Exactly-once flip shared with the delivery job: whichever runs
            // first wins; delivery still mints the fresh token and sends.
            $firstSend = $this->builder->markDelivered($changeOrder);

            if ($firstSend && $notifiedUsers !== []) {
                Notification::send($notifiedUsers, new ChangeOrderSentNotification($changeOrder));
            }

            return $changeOrder;
        });
    }

    /**
     * Idempotent per recipient *while the intent is still undelivered*.
     *
     * Re-running "send" against a READY/SCHEDULED/PENDING row returns that row
     * rather than piling up duplicates. Once the row is terminal (SENT, FAILED,
     * CANCELLED, SUPPRESSED) the manager's click is a genuine re-send request, so
     * a NEW row is recorded with an attempt-suffixed key. Returning the sent row
     * here would silently swallow every re-send: the customer never hears again
     * and the outbox looks like nothing happened.
     */
    private function recordIntent(
        ChangeOrder $changeOrder,
        object $user,
        string $subject,
        array $sender,
    ): void {
        $baseKey = $this->idempotencyKey($changeOrder, $user->email);

        $existing = EmailLog::query()->where('idempotency_key', $baseKey)->first();

        if ($existing !== null && $existing->status->isDeliverable()) {
            return; // still an open outbox intent for this recipient
        }

        EmailLog::create([
            'project_id' => $changeOrder->project_id,
            'change_order_id' => $changeOrder->id,
            'recipient_email' => $user->email,
            'recipient_name' => $user->name,
            'sender_email' => $sender['address'],
            'sender_name' => $sender['name'] !== '' ? $sender['name'] : null,
            'cc_emails' => $changeOrder->creator?->email !== null ? [$changeOrder->creator->email] : [],
            'subject' => $subject,
            'body_preview' => 'Change order awaiting manual release in the Customer Emails outbox.',
            'type' => EmailType::CHANGE_ORDER,
            'status' => EmailStatus::READY,
            'context_id' => (string) $changeOrder->id,
            // No payload: the DTO (including the raw token link) is built
            // fresh at delivery. Tokens never touch the database.
            'payload' => null,
            'idempotency_key' => $this->resendKey($changeOrder, $user->email, $existing),
        ]);
    }

    /**
     * The base key for a first send, or an attempt-suffixed key when rows with
     * that base key already exist. A sha256 key is exactly 64 chars — the
     * column's width — so the base is truncated by exactly the length of the
     * suffix and always fits, whatever the attempt number. Mirrors
     * DeliverCustomerEmailAction::resendKey().
     */
    private function resendKey(ChangeOrder $changeOrder, string $email, ?EmailLog $existing): string
    {
        $base = $this->idempotencyKey($changeOrder, $email);

        if ($existing === null) {
            return $base; // first send for this recipient
        }

        // Count every sibling row for the same recipient so repeated re-sends
        // keep minting distinct keys instead of colliding with attempt 2 twice.
        $attempt = (int) EmailLog::query()
            ->where('change_order_id', $changeOrder->id)
            ->where('recipient_email', $email)
            ->count();

        $suffix = '|a:'.$attempt;

        return substr($base, 0, 64 - strlen($suffix)).$suffix;
    }

    private function idempotencyKey(ChangeOrder $changeOrder, string $email): string
    {
        return hash('sha256', implode('|', [
            EmailType::CHANGE_ORDER->value,
            'null',
            $email,
            (string) $changeOrder->id,
        ]));
    }
}
