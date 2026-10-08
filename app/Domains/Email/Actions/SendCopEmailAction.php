<?php

declare(strict_types=1);

namespace App\Domains\Email\Actions;

use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\Enums\DeliveryMode;
use App\Domains\Email\Enums\EmailStatus;
use App\Domains\Email\Enums\EmailType;
use App\Domains\Email\Jobs\SendCopEmailJob;
use App\Domains\Email\Models\EmailLog;
use App\Domains\Email\Services\EmailAttachmentPolicy;
use App\Domains\Settings\Services\MailSettingsService;
use App\Shared\Exceptions\EmailNotConfiguredException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Records an email intent and, for automatic emails, hands it to the queue.
 *
 * Delivery policy lives in EmailType::deliveryMode():
 *  - NONE     → nothing recorded, nothing sent (e.g. portal revoke).
 *  - MANUAL   → a READY email_logs row; a manager releases it from the
 *               Customer Emails outbox. The customer kill switch does NOT
 *               block recording — only delivery — so intents survive the
 *               switch being toggled off and back on.
 *  - AUTO/INTERNAL → record PENDING and dispatch immediately; INTERNAL
 *               (staff) emails never hit the kill switch.
 */
final class SendCopEmailAction
{
    /** Audit note written to suppressed email_logs rows. */
    public const SUPPRESSED_REASON = 'Customer emails are currently disabled (CUSTOMER_EMAILS_ENABLED=false).';

    /** Audit note for types with DeliveryMode::NONE — no row, logged only. */
    public const UNRECORDED_NOTE = 'Email type is configured to never email (DeliveryMode::NONE).';

    public function __construct(
        private readonly MailSettingsService $mailSettings,
        private readonly EmailAttachmentPolicy $attachmentPolicy,
    ) {}

    public function execute(SendEmailDTO $dto): EmailLog
    {
        $mode = $dto->type->deliveryMode();

        if ($mode === DeliveryMode::NONE) {
            // Never emailed: not even for audit. The database notification
            // (if any) still goes out through its own channel.
            return $this->unrecorded($dto);
        }

        if ($mode === DeliveryMode::MANUAL) {
            // The stored payload keeps the RAW dto: at delivery the job re-runs
            // the attachment policy, so a scheduled PDF email gets a freshly
            // signed link when it actually goes out (not a link that expires
            // while it waits). Only the human-readable preview is resolved
            // now, so the outbox shows the manager what will be sent.
            $preview = $this->attachmentPolicy->apply($dto);

            return $this->record($dto, EmailStatus::READY, preview: $preview);
        }

        if (! $this->customerEmailsEnabled() && $dto->type->isCustomerFacing()) {
            return $this->suppress($dto);
        }

        $log = $this->record($dto, EmailStatus::PENDING);

        SendCopEmailJob::dispatch($log, $dto);

        return $log;
    }

    private function unrecorded(SendEmailDTO $dto): never
    {
        Log::info('Customer email delivery mode NONE — not recorded', [
            'type' => $dto->type->value,
            'recipient' => $dto->to,
            'note' => self::UNRECORDED_NOTE,
        ]);

        // ponytail: NONE types throw instead of returning a ghost EmailLog.
        // A phantom model with no id invited null-id bugs downstream; every
        // current caller is a notification channel that tolerates the throw
        // (queued notify retries) or a controller that surfaces it. If a
        // future caller needs soft-skip semantics, return an Option instead.
        throw new EmailNotConfiguredException($dto->type, self::UNRECORDED_NOTE);
    }

    /**
     * Insert the audit row, resolving idempotency races the same way as
     * before: same deterministic key → return the existing row.
     *
     * @throws UniqueConstraintViolationException only if the racing row vanished
     */
    private function record(
        SendEmailDTO $dto,
        EmailStatus $status,
        ?SendEmailDTO $preview = null,
    ): EmailLog {
        $key = $this->idempotencyKey($dto);

        $attributes = [
            'closeout_package_id' => $dto->closeoutPackageId,
            'project_id' => $dto->projectId,
            'recipient_email' => $dto->to,
            'recipient_name' => $dto->toName,
            'sender_email' => $this->mailSettings->getSender()['address'],
            'sender_name' => $this->senderName(),
            'cc_emails' => $dto->cc,
            'subject' => $dto->subject,
            'body_preview' => Str::limit(($preview ?? $dto)->body, 500),
            'type' => $dto->type,
            'status' => $status,
            'context_id' => $dto->contextId,
            'payload' => $dto->toArray(),
            'idempotency_key' => $key,
        ];

        if ($status === EmailStatus::SUPPRESSED) {
            $attributes['failed_reason'] = self::SUPPRESSED_REASON;
        }

        if ($key !== null) {
            $existing = EmailLog::query()->where('idempotency_key', $key)->first();

            if ($existing !== null) {
                return $this->reuseExisting($dto, $existing, $status);
            }
        }

        try {
            return EmailLog::create($attributes);
        } catch (UniqueConstraintViolationException) {
            // Race: a concurrent request inserted the same idempotency key first.
            $existing = EmailLog::query()->where('idempotency_key', $key)->firstOrFail();

            return $this->reuseExisting($dto, $existing, $status);
        }
    }

    private function reuseExisting(SendEmailDTO $dto, EmailLog $existing, EmailStatus $incoming): EmailLog
    {
        // Only automatic dispatches may resurrect a row, and only when it was
        // never delivered (failed or suppressed). A SENT row is never touched
        // here — resending a sent email is an explicit manager action in the
        // outbox, which creates a NEW row (DeliverCustomerEmailAction).
        $retryable = in_array($existing->status, [EmailStatus::FAILED, EmailStatus::SUPPRESSED], true);

        if ($incoming === EmailStatus::PENDING && $retryable) {
            Log::info('Retrying previously failed or suppressed email', ['idempotency_key' => $existing->idempotency_key]);

            $existing->update(['status' => EmailStatus::PENDING]);

            SendCopEmailJob::dispatch($existing, $dto);

            return $existing;
        }

        Log::debug('Duplicate email detected', ['idempotency_key' => $existing->idempotency_key]);

        return $existing;
    }

    private function suppress(SendEmailDTO $dto): EmailLog
    {
        Log::info('Customer email suppressed by kill switch', [
            'type' => $dto->type->value,
            'recipient' => $dto->to,
        ]);

        return $this->record($dto, EmailStatus::SUPPRESSED);
    }

    private function customerEmailsEnabled(): bool
    {
        return (bool) config('cop.customer_emails_enabled', false);
    }

    private function senderName(): ?string
    {
        $name = $this->mailSettings->getSender()['name'];

        return $name !== '' ? $name : null;
    }

    private function idempotencyKey(SendEmailDTO $dto): ?string
    {
        if ($dto->type === EmailType::GENERAL) {
            return null;
        }

        return hash('sha256', implode('|', [
            $dto->type->value,
            $dto->closeoutPackageId !== null ? (string) $dto->closeoutPackageId : 'null',
            $dto->to,
            $dto->contextId ?? 'null',
        ]));
    }
}
