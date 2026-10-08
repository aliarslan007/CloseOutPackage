<?php

declare(strict_types=1);

namespace App\Domains\Email\Jobs;

use App\Domains\Email\Actions\LogEmailAction;
use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\Events\EmailDelivered;
use App\Domains\Email\Events\EmailFailed;
use App\Domains\Email\Models\EmailLog;
use App\Domains\Email\Services\DynamicMailSender;
use App\Domains\Email\Services\EmailAttachmentPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Delivers one email_log row. Constructed two ways:
 *  - with a DTO (AUTO/INTERNAL path, unchanged) — SerializesModels keeps a
 *    reference to the row, refreshed on unserialize;
 *  - with only the log (outbox path) — the DTO is hydrated from the row's
 *    stored payload, so nothing durable lives in the queue payload except
 *    the log id.
 */
class SendCopEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly EmailLog $log,
        public readonly ?SendEmailDTO $dto = null,
        public readonly ?string $expectedScheduledAt = null,
    ) {}

    public function handle(
        DynamicMailSender $sender,
        EmailAttachmentPolicy $attachmentPolicy,
        LogEmailAction $logEmail,
    ): void {
        // Resolve by id: the row may have been cancelled (or, for the audit
        // purge, deleted) after this job was dispatched.
        $log = EmailLog::query()->find($this->log->id);

        if ($log === null) {
            return; // purged audit row — nothing to deliver, nothing to report
        }

        if (! $log->status->isDeliverable()) {
            return; // cancelled/suppressed/sent already — silently no-op
        }

        // A delayed job carries the scheduled_at it was dispatched with. If
        // the row has since been rescheduled (or released early, which clears
        // scheduled_at), this job is superseded and must not send.
        if ($this->expectedScheduledAt !== null
            && $log->scheduled_at?->toIso8601String() !== $this->expectedScheduledAt
        ) {
            return;
        }

        $dto = $this->dto ?? $this->hydrateFromPayload($log);

        if ($dto === null) {
            app(LogEmailAction::class)->markFailed($log, 'Stored email payload is missing or unreadable.');

            return;
        }

        // Attachment policy runs NOW, not at record time: a scheduled PDF
        // email gets a fresh signed link moments before it goes out.
        $dto = $attachmentPolicy->apply($dto);

        try {
            $messageId = $sender->send($dto);

            $logEmail->markSent($log, $messageId);

            EmailDelivered::dispatch($log);
        } catch (Throwable $exception) {
            if ($this->attempts() >= $this->tries) {
                $logEmail->markFailed($log, $exception->getMessage());

                EmailFailed::dispatch($log, $exception->getMessage());
            }

            throw $exception;
        }
    }

    /**
     * Rebuild the DTO from the row's stored payload (MANUAL intents recorded
     * by SendCopEmailAction).
     */
    private function hydrateFromPayload(EmailLog $log): ?SendEmailDTO
    {
        $payload = $log->payload;

        if (! is_array($payload) || ! isset($payload['to'])) {
            return null;
        }

        return SendEmailDTO::fromArray($payload);
    }
}
