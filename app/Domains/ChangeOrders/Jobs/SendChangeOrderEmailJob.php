<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Jobs;

use App\Domains\ChangeOrders\Actions\BuildChangeOrderCustomerEmailAction;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\Email\Actions\LogEmailAction;
use App\Domains\Email\Events\EmailDelivered;
use App\Domains\Email\Events\EmailFailed;
use App\Domains\Email\Models\EmailLog;
use App\Domains\Email\Services\DynamicMailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Delivers the customer email for one change-order outbox row.
 *
 * The row was recorded as a READY intent by SendChangeOrderAction with only
 * change_order_id + recipient — no tokenized payload. This job, running at
 * the moment of delivery (now or on a delayed schedule), mints the fresh
 * decision token, marks the change order delivered exactly once, sends, and
 * writes the outcome onto the existing row. The raw token exists only inside
 * this job's in-memory DTO.
 */
final class SendChangeOrderEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly EmailLog $log,
        public readonly ?string $expectedScheduledAt = null,
    ) {}

    public function handle(
        DynamicMailSender $sender,
        BuildChangeOrderCustomerEmailAction $builder,
        LogEmailAction $logEmail,
    ): void {
        $log = EmailLog::query()->find($this->log->id);

        if ($log === null || ! $log->status->isDeliverable()) {
            return; // purged, cancelled or already sent — nothing to do
        }

        // Superseded by a reschedule (or released early)? Then no-op.
        if ($this->expectedScheduledAt !== null
            && $log->scheduled_at?->toIso8601String() !== $this->expectedScheduledAt
        ) {
            return;
        }

        /** @var ChangeOrder|null $changeOrder */
        $changeOrder = ChangeOrder::query()
            ->with(['project.market', 'creator', 'items', 'recipients.user'])
            ->find($log->change_order_id);

        if ($changeOrder === null) {
            $logEmail->markFailed($log, 'The change order for this email no longer exists.');

            return;
        }

        $recipient = $this->resolveRecipient($log, $changeOrder);

        if ($recipient === null || $recipient->user === null) {
            $logEmail->markFailed($log, 'The customer recipient for this email no longer exists on the change order.');

            return;
        }

        try {
            $rawToken = $builder->mintToken($changeOrder, $recipient);
            $respondUrl = route('change-orders.token.show', ['token' => $rawToken]);
            $dto = $builder->buildDTO($changeOrder, $recipient->user, $respondUrl);

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
     * The outbox row stores only the recipient's email; map it back to the
     * change-order recipient so a fresh token is minted for the right user.
     */
    private function resolveRecipient(EmailLog $log, ChangeOrder $changeOrder): ?object
    {
        return $changeOrder->recipients
            ->first(fn ($candidate): bool => $candidate->user !== null && $candidate->user->email === $log->recipient_email);
    }
}
