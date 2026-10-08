import * as React from 'react';
import type { ChangeOrder } from '@/components/change-orders/change-order-types';
import { formatCurrency, formatDate, formatTime } from './change-order-format';

export interface ChangeOrderTimelineProps {
    changeOrder: ChangeOrder;
    creatorName: string;
}

const DECISION_EVENTS: Record<
    string,
    { title: string; copy: string; ring: string; bg: string }
> = {
    approved: {
        title: 'Change Order Approved',
        copy: 'All items approved by the customer. Total authorized:',
        ring: 'ring-emerald-500/70',
        bg: 'bg-emerald-500/15',
    },
    partially_approved: {
        title: 'Change Order Partially Approved',
        copy: 'Some items approved and some rejected by the customer.',
        ring: 'ring-violet-500/70',
        bg: 'bg-violet-500/15',
    },
    rejected: {
        title: 'Change Order Rejected',
        copy: 'All items rejected by the customer.',
        ring: 'ring-red-500/70',
        bg: 'bg-red-500/15',
    },
};

/**
 * Activity history: draft, send, first read and the customer decision.
 */
export const ChangeOrderTimeline = React.memo(function ChangeOrderTimeline({
    changeOrder,
    creatorName,
}: Readonly<ChangeOrderTimelineProps>) {
    const recipients = React.useMemo(() => changeOrder.recipients ?? [], [changeOrder.recipients]);

    // First read ever — earliest viewed_at wins and is never rewritten.
    const firstRead = React.useMemo(() => {
        return recipients
            .filter((r): r is (typeof r) & { viewed_at: string } => Boolean(r.viewed_at))
            .sort((a, b) => a.viewed_at.localeCompare(b.viewed_at))[0];
    }, [recipients]);

    // Customer decision point — the recipient's recorded response time.
    const lastResponse = React.useMemo(() => {
        return recipients
            .filter((r): r is (typeof r) & { responded_at: string } => Boolean(r.responded_at))
            .sort((a, b) => b.responded_at.localeCompare(a.responded_at))[0];
    }, [recipients]);

    const decisionEvent = React.useMemo(() => {
        return DECISION_EVENTS[changeOrder.status] ?? null;
    }, [changeOrder.status]);

    return (
        <div className="h-full rounded-xl border border-border/80 bg-card p-5 sm:p-6 shadow-xs text-xs">
            <div className="relative pl-6 space-y-6 before:absolute before:left-[9px] before:top-2 before:bottom-2 before:w-0.5 before:bg-border">
                {/* Event: Created */}
                <div className="relative flex items-start gap-3">
                    <span className="absolute -left-6 top-0.5 z-10 size-5 rounded-full bg-muted border-4 border-card ring-1 ring-primary" />
                    <div>
                        <p className="font-bold text-foreground">Change Order Draft Created</p>
                        <p className="text-muted-foreground mt-0.5">
                            Created by {creatorName} on {formatDate(changeOrder.created_at)}
                        </p>
                    </div>
                </div>

                {/* Event: Date of Occurrence */}
                {changeOrder.change_order_date && (
                    <div className="relative flex items-start gap-3">
                        <span className="absolute -left-6 top-0.5 z-10 size-5 rounded-full bg-emerald-500/15 border-4 border-card ring-1 ring-emerald-500/70" />
                        <div>
                            <p className="font-bold text-foreground">Date of Occurrence</p>
                            <p className="text-muted-foreground mt-0.5">
                                On-site work occurred / scheduled for{' '}
                                {formatDate(changeOrder.change_order_date)}
                            </p>
                        </div>
                    </div>
                )}

                {/* Event: Sent */}
                {changeOrder.sent_at && (
                    <div className="relative flex items-start gap-3">
                        <span className="absolute -left-6 top-0.5 z-10 size-5 rounded-full bg-blue-500/15 border-4 border-card ring-1 ring-blue-500/70" />
                        <div>
                            <p className="font-bold text-foreground">Sent to Customer(s)</p>
                            <p className="text-muted-foreground mt-0.5">
                                Sent by email with decision access on{' '}
                                {formatDate(changeOrder.sent_at)} at{' '}
                                {formatTime(changeOrder.sent_at)}
                            </p>
                        </div>
                    </div>
                )}

                {/* Event: Read by customer — first open, link or portal, immutable */}
                {firstRead && (
                    <div className="relative flex items-start gap-3">
                        <span className="absolute -left-6 top-0.5 z-10 size-5 rounded-full bg-sky-500/15 border-4 border-card ring-1 ring-sky-500/70" />
                        <div>
                            <p className="font-bold text-foreground">Read by Customer</p>
                            <p className="text-muted-foreground mt-0.5">
                                {firstRead.user?.name ?? 'Customer'} opened the change order on{' '}
                                {formatDate(firstRead.viewed_at)} at{' '}
                                {formatTime(firstRead.viewed_at)}
                            </p>
                        </div>
                    </div>
                )}

                {/* Event: Decision */}
                {decisionEvent && (
                    <div className="relative flex items-start gap-3">
                        <span
                            className={`absolute -left-6 top-0.5 z-10 size-5 rounded-full border-4 border-card ${decisionEvent.bg} ${decisionEvent.ring}`}
                        />
                        <div>
                            <p className="font-bold text-foreground">{decisionEvent.title}</p>
                            <p className="text-muted-foreground mt-0.5">
                                {decisionEvent.copy}
                                {changeOrder.status === 'approved' &&
                                    ` ${formatCurrency(changeOrder.total_amount)}.`}
                                {lastResponse && (
                                    <>
                                        {' '}
                                        Recorded on {formatDate(lastResponse.responded_at)} at{' '}
                                        {formatTime(lastResponse.responded_at)} by{' '}
                                        {lastResponse.user?.name ?? 'the customer'}.
                                    </>
                                )}
                            </p>
                        </div>
                    </div>
                )}

                {/* Event: Workflow complete — terminal step, no further action */}
                {decisionEvent && (
                    <div className="relative flex items-start gap-3">
                        <span className="absolute -left-6 top-0.5 z-10 size-5 rounded-full bg-muted border-4 border-card ring-1 ring-primary" />
                        <div>
                            <p className="font-bold text-foreground">Change Order Workflow Completed</p>
                            <p className="text-muted-foreground mt-0.5">
                                No further action is required on this change order.
                                {lastResponse &&
                                    ` Completed on ${formatDate(lastResponse.responded_at)} at ${formatTime(lastResponse.responded_at)}.`}
                            </p>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
});

export default ChangeOrderTimeline;
