import { Badge } from '@/components/ui/badge';
import { EmailLogItem } from '@/components/email-logs/email-log-labels';
import { Clock } from 'lucide-react';

/**
 * Outbox-specific status labels and badge variants. Distinct from the audit
 * pages' EMAIL_STATUS_LABELS: here "Not sent" is an actionable intent, not
 * a delivery failure, and the manager needs the schedule/sent context inline.
 */

const OUTBOX_STATUS: Record<string, { label: string; variant: 'warning' | 'info' | 'success' | 'destructive' | 'secondary' }> = {
    ready: { label: 'Not sent', variant: 'warning' },
    scheduled: { label: 'Scheduled', variant: 'info' },
    pending: { label: 'Sending', variant: 'info' },
    sent: { label: 'Sent', variant: 'success' },
    failed: { label: 'Failed', variant: 'destructive' },
};

export function CustomerEmailStatusBadge({ log }: { log: EmailLogItem }) {
    const meta = OUTBOX_STATUS[log.status] ?? { label: log.status, variant: 'secondary' as const };

    return (
        <div className="flex flex-col items-start gap-0.5">
            <Badge variant={meta.variant} className="text-[10px]">
                {meta.label}
            </Badge>
            {log.status === 'scheduled' && log.scheduled_at && (
                <span className="flex items-center gap-1 text-[10px] text-muted-foreground">
                    <Clock className="size-3" />
                    {new Date(log.scheduled_at).toLocaleString()}
                </span>
            )}
            {log.status === 'sent' && log.sent_at && (
                <span className="text-[10px] text-muted-foreground">on {new Date(log.sent_at).toLocaleDateString()}</span>
            )}
            {log.status === 'failed' && log.failed_reason && (
                <span className="max-w-[180px] truncate text-[10px] text-destructive" title={log.failed_reason}>
                    {log.failed_reason}
                </span>
            )}
        </div>
    );
}

function getTimestampValue(log: EmailLogItem): string {
    if (log.status === 'sent' && log.sent_at) {
        return log.sent_at;
    }
    if (log.status === 'scheduled' && log.scheduled_at) {
        return log.scheduled_at;
    }
    return log.created_at;
}

/** Timestamp column: the moment that matters for this row's current state. */
export function CustomerEmailTimestamp({ log }: { log: EmailLogItem }) {
    const value = getTimestampValue(log);

    return (
        <span className="whitespace-nowrap text-muted-foreground">
            {new Date(value).toLocaleString()}
        </span>
    );
}

