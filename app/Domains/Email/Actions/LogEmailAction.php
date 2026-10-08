<?php

declare(strict_types=1);

namespace App\Domains\Email\Actions;

use App\Domains\Email\Enums\EmailStatus;
use App\Domains\Email\Models\EmailLog;

final class LogEmailAction
{
    public function markSent(EmailLog $log, ?string $messageId = null): void
    {
        $log->update([
            'status' => EmailStatus::SENT,
            'sent_at' => now(),
            'provider_message_id' => $messageId,
            'failed_reason' => null,
        ]);
    }

    public function markFailed(EmailLog $log, string $reason): void
    {
        $log->update([
            'status' => EmailStatus::FAILED,
            'failed_reason' => $reason,
        ]);
    }
}
